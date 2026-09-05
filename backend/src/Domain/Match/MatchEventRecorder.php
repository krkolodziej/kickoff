<?php

declare(strict_types=1);

namespace App\Domain\Match;

use App\Entity\Fixture;
use App\Entity\MatchEvent;
use App\Entity\MatchEventType;
use App\Entity\Player;
use App\Entity\Team;
use App\Exception\MatchEventRuleException;
use App\Message\MatchUpdated;
use App\Repository\RosterEntryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * The rules about who can do what, and when.
 *
 * Every check here exists because the alternative is a league table that is quietly wrong: a
 * goal credited to a player who was not registered, a card for a club that was not playing, a
 * score that moved without an event behind it.
 *
 * The single most important line is the transaction. A goal is *two* changes — the event row
 * and the score — and they have to be one act. Recorded separately, a failure between them
 * leaves a score that no longer matches its own history, and nothing in the application would
 * ever notice.
 */
final class MatchEventRecorder
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RosterEntryRepository $rosterEntries,
        private readonly MessageBusInterface $bus,
    ) {
    }

    public function record(
        Fixture $fixture,
        MatchEventType $type,
        int $minute,
        Team $team,
        Player $player,
        ?Player $relatedPlayer = null,
    ): MatchEvent {
        // 1. Only while the match is being played. Recording a goal on a scheduled match is
        //    how a season ends up with results nobody played.
        if (!$fixture->isLive()) {
            throw MatchEventRuleException::notLive($fixture->getStatus()->value);
        }

        // 2. The club has to be one of the two on the pitch.
        if (!$fixture->involves($team)) {
            throw MatchEventRuleException::forField('team_id', 'That club is not playing in this match.');
        }

        $season = $fixture->getSeason();

        // 3. And the player has to be in that club's squad *for this season*.
        if (!$this->rosterEntries->isRosteredFor($season, $team, $player)) {
            throw MatchEventRuleException::forField(
                'player_id',
                'That player is not in this club\'s squad for this season.',
            );
        }

        if ($type->needsRelatedPlayer()) {
            $this->guardSubstitution($fixture, $team, $player, $relatedPlayer);
        } elseif (null !== $relatedPlayer) {
            // A second player on a goal or a card means the caller has confused two event
            // types. Accepting it silently would store data nothing knows how to read.
            throw MatchEventRuleException::forField(
                'related_player_id',
                'Only a substitution involves a second player.',
            );
        }

        return $this->entityManager->wrapInTransaction(
            function () use ($fixture, $type, $minute, $team, $player, $relatedPlayer): MatchEvent {
                $event = new MatchEvent($fixture, $type, $minute, $team, $player);
                $event->setRelatedPlayer($type->needsRelatedPlayer() ? $relatedPlayer : null);

                $this->entityManager->persist($event);

                // The score and the event, in one transaction. Neither exists without the
                // other.
                if ($type->movesTheScore()) {
                    $fixture->recordGoalFor($team);
                }

                // Inside the transaction on purpose. Publishing to a hub is an HTTP call that
                // cannot be taken back, so it goes through the queue, whose rows roll back
                // with everything else: a goal that fails to save never reaches a screen.
                $this->bus->dispatch(new MatchUpdated((int) $fixture->getId()));

                return $event;
            },
        );
    }

    private function guardSubstitution(
        Fixture $fixture,
        Team $team,
        Player $player,
        ?Player $relatedPlayer,
    ): void {
        if (null === $relatedPlayer) {
            throw MatchEventRuleException::forField(
                'related_player_id',
                'A substitution needs the player coming on.',
            );
        }

        if ($relatedPlayer->getId() === $player->getId()) {
            throw MatchEventRuleException::forField(
                'related_player_id',
                'A player cannot be substituted for themselves.',
            );
        }

        if (!$this->rosterEntries->isRosteredFor($fixture->getSeason(), $team, $relatedPlayer)) {
            throw MatchEventRuleException::forField(
                'related_player_id',
                'The player coming on is not in this club\'s squad for this season.',
            );
        }
    }
}
