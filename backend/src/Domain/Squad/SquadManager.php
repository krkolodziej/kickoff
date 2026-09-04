<?php

declare(strict_types=1);

namespace App\Domain\Squad;

use App\Entity\Player;
use App\Entity\PlayerPosition;
use App\Entity\RosterEntry;
use App\Entity\Season;
use App\Entity\SeasonTeam;
use App\Entity\Team;
use App\Exception\ConflictException;
use App\Exception\SquadRuleException;
use App\Repository\RosterEntryRepository;
use App\Repository\SeasonTeamRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Who may be registered for a season, and who may be in a squad.
 *
 * These rules live here rather than in constraints for one reason: they all need to know
 * *which organization is asking*, and a validator has no way to find that out. A constraint
 * validates a value; this validates a value against a context.
 */
final class SquadManager
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SeasonTeamRepository $seasonTeams,
        private readonly RosterEntryRepository $rosterEntries,
    ) {
    }

    public function registerTeam(Season $season, Team $team): SeasonTeam
    {
        // The club has to come from the same organization as the competition. Without this,
        // an admin could register any club whose id they could guess — the ids are sequential
        // and the endpoint is otherwise perfectly legitimate.
        if ($team->getOrganization()->getId() !== $season->getOrganization()->getId()) {
            throw SquadRuleException::forField('team_id', 'That club belongs to another organization.');
        }

        if ($this->seasonTeams->isRegistered($season, $team)) {
            throw new ConflictException('That club is already registered for this season.', 'already_registered');
        }

        $seasonTeam = new SeasonTeam($season, $team);

        $this->entityManager->persist($seasonTeam);
        $this->entityManager->flush();

        return $seasonTeam;
    }

    public function withdrawTeam(SeasonTeam $seasonTeam): void
    {
        $this->entityManager->remove($seasonTeam);
        $this->entityManager->flush();
    }

    public function addToSquad(
        SeasonTeam $seasonTeam,
        Player $player,
        ?int $shirtNumber,
        ?PlayerPosition $position,
        bool $captain,
    ): RosterEntry {
        if ($player->getOrganization()->getId() !== $seasonTeam->getSeason()->getOrganization()->getId()) {
            throw SquadRuleException::forField('player_id', 'That player belongs to another organization.');
        }

        if ($this->rosterEntries->playerIsInSquad($seasonTeam, $player)) {
            throw new ConflictException('That player is already in this squad.', 'already_in_squad');
        }

        $entry = new RosterEntry($seasonTeam, $player);

        return $this->entityManager->wrapInTransaction(
            function () use ($entry, $seasonTeam, $shirtNumber, $position, $captain): RosterEntry {
                $this->entityManager->persist($entry);
                $this->apply($seasonTeam, $entry, $shirtNumber, $position, $captain);

                return $entry;
            },
        );
    }

    public function updateSquadEntry(
        RosterEntry $entry,
        ?int $shirtNumber,
        ?PlayerPosition $position,
        bool $captain,
    ): void {
        $this->entityManager->wrapInTransaction(
            function () use ($entry, $shirtNumber, $position, $captain): void {
                $this->apply($entry->getSeasonTeam(), $entry, $shirtNumber, $position, $captain);
            },
        );
    }

    public function removeFromSquad(RosterEntry $entry): void
    {
        $this->entityManager->remove($entry);
        $this->entityManager->flush();
    }

    /**
     * The shared write path, so the shirt-number check and the captain handover are stated
     * once rather than in both the add and the update branch.
     */
    private function apply(
        SeasonTeam $seasonTeam,
        RosterEntry $entry,
        ?int $shirtNumber,
        ?PlayerPosition $position,
        bool $captain,
    ): void {
        if (null !== $shirtNumber && $this->rosterEntries->shirtNumberTaken($seasonTeam, $shirtNumber, $entry->getId())) {
            // Checked rather than left to the unique index, because an integrity violation
            // surfaces as a 500. The index is still the guarantee; this is the message.
            throw SquadRuleException::forField(
                'shirt_number',
                \sprintf('Number %d is already worn in this squad.', $shirtNumber),
            );
        }

        $entry->setShirtNumber($shirtNumber);
        $entry->setPosition($position);

        if ($captain) {
            // Naming a captain demotes the previous one instead of failing. A squad has
            // exactly one, and refusing the request would make the operator hunt for who
            // currently holds it — a rule the computer is better placed to keep than a
            // person is.
            $current = $this->rosterEntries->currentCaptain($seasonTeam);

            if (null !== $current && $current !== $entry) {
                $current->setCaptain(false);

                // Flushed on its own, before the new captain is promoted. Doctrine orders
                // every INSERT ahead of every UPDATE within one flush, so leaving both
                // changes to a single unit of work would insert the new captain while the
                // old flag was still set — which the partial unique index rejects, correctly.
                // Both flushes sit inside the caller's transaction, so the handover is still
                // one atomic act.
                $this->entityManager->flush();
            }
        }

        $entry->setCaptain($captain);

        $this->entityManager->flush();
    }
}
