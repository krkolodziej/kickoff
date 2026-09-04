<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Domain\Notification\Notifier;
use App\Entity\Fixture;
use App\Entity\MatchStatus;
use App\Entity\NotificationType;
use App\Message\MatchFinished;
use App\Repository\FixtureRepository;
use App\Repository\OrganizationMembershipRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Tells the people who run an organization that one of its matches has finished.
 *
 * Every early return here is a case that will happen. A worker can pick this message up long
 * after it was sent — after the season was deleted, after the match was reopened and cancelled
 * — and "the world moved on" is not an error worth retrying. Throwing would put the message
 * on the failed transport and ask a human to look at something that is simply no longer true.
 */
#[AsMessageHandler]
final readonly class MatchFinishedHandler
{
    public function __construct(
        private FixtureRepository $fixtures,
        private OrganizationMembershipRepository $memberships,
        private Notifier $notifier,
    ) {
    }

    public function __invoke(MatchFinished $message): void
    {
        $fixture = $this->fixtures->find($message->fixtureId);

        if (null === $fixture) {
            return;
        }

        // The message says a match finished; the row is what says whether it still has. A
        // match reopened and cancelled between the dispatch and the handling should not
        // announce a result nobody will find.
        if (MatchStatus::Finished !== $fixture->getStatus()) {
            return;
        }

        $season = $fixture->getSeason();
        $organization = $season->getOrganization();

        $this->notifier->deliver(
            recipients: $this->memberships->managersOf($organization),
            organization: $organization,
            type: NotificationType::MatchFinished,
            subject: (string) $fixture->getId(),
            title: \sprintf(
                '%s %d–%d %s',
                $fixture->getHomeTeam()->getShortName(),
                $fixture->getHomeScore(),
                $fixture->getAwayScore(),
                $fixture->getAwayTeam()->getShortName(),
            ),
            body: \sprintf(
                'Round %d of %s has a result: %s %d–%d %s.',
                $fixture->getRoundNumber(),
                $season->getName(),
                $fixture->getHomeTeam()->getName(),
                $fixture->getHomeScore(),
                $fixture->getAwayScore(),
                $fixture->getAwayTeam()->getName(),
            ),
            link: self::link($fixture),
        );
    }

    private static function link(Fixture $fixture): string
    {
        $season = $fixture->getSeason();

        return \sprintf(
            '/organizations/%d/leagues/%d/seasons/%d/fixtures/%d',
            (int) $season->getOrganization()->getId(),
            (int) $season->getLeague()->getId(),
            (int) $season->getId(),
            (int) $fixture->getId(),
        );
    }
}
