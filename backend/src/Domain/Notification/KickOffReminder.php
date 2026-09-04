<?php

declare(strict_types=1);

namespace App\Domain\Notification;

use App\Entity\Fixture;
use App\Entity\NotificationType;
use App\Repository\FixtureRepository;
use App\Repository\OrganizationMembershipRepository;
use Psr\Clock\ClockInterface;

/**
 * "This match is tomorrow.".
 *
 * Written as a plain service rather than inside the message handler, because two things need
 * to run it: the schedule, every quarter of an hour, and `app:matches:remind`, when somebody
 * wants to know what it would do without waiting. A handler that contained the logic could
 * only be invoked by dispatching a message, which makes it awkward to look at.
 *
 * `ClockInterface` rather than `new DateTimeImmutable()`. This is the class the interface was
 * introduced for back in Stage 5: a job whose whole behaviour is "which matches are roughly
 * a day away" can only be tested by fixing what "now" means.
 */
final readonly class KickOffReminder
{
    /**
     * A window rather than an instant, and half the cadence either side of it.
     *
     * The schedule runs every fifteen minutes, so a window of ±15 minutes around "in a day"
     * is exactly wide enough that every match falls into one run and narrow enough that none
     * falls into two. Widen it and matches get reminded twice — which the dedupe key would
     * absorb, but silently, hiding the mistake. Narrow it and a run that starts a few seconds
     * late misses matches entirely, which nothing absorbs.
     */
    private const HOURS_AHEAD = 24;
    private const WINDOW_MINUTES = 15;

    public function __construct(
        private FixtureRepository $fixtures,
        private OrganizationMembershipRepository $memberships,
        private Notifier $notifier,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return array{matches: int, notifications: int}
     */
    public function run(): array
    {
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        $centre = $now->modify(\sprintf('+%d hours', self::HOURS_AHEAD));

        $fixtures = $this->fixtures->findKickingOffBetween(
            $centre->modify(\sprintf('-%d minutes', self::WINDOW_MINUTES)),
            $centre->modify(\sprintf('+%d minutes', self::WINDOW_MINUTES)),
        );

        $notifications = 0;

        foreach ($fixtures as $fixture) {
            $notifications += $this->remind($fixture);
        }

        return ['matches' => \count($fixtures), 'notifications' => $notifications];
    }

    private function remind(Fixture $fixture): int
    {
        $season = $fixture->getSeason();
        $organization = $season->getOrganization();
        $kickOff = $fixture->getKickOffAt();

        return $this->notifier->deliver(
            recipients: $this->memberships->managersOf($organization),
            organization: $organization,
            type: NotificationType::KickOffReminder,
            subject: (string) $fixture->getId(),
            title: \sprintf(
                '%s v %s tomorrow',
                $fixture->getHomeTeam()->getShortName(),
                $fixture->getAwayTeam()->getShortName(),
            ),
            body: \sprintf(
                '%s play %s in round %d of %s%s.',
                $fixture->getHomeTeam()->getName(),
                $fixture->getAwayTeam()->getName(),
                $fixture->getRoundNumber(),
                $season->getName(),
                null === $kickOff ? '' : ', at '.$kickOff->format('H:i'),
            ),
            link: \sprintf(
                '/organizations/%d/leagues/%d/seasons/%d/fixtures/%d',
                (int) $organization->getId(),
                (int) $season->getLeague()->getId(),
                (int) $season->getId(),
                (int) $fixture->getId(),
            ),
        );
    }
}
