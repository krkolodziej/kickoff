<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Domain\Notification\KickOffReminder;
use App\Domain\Notification\Notifier;
use App\Entity\Fixture;
use App\Entity\MatchStatus;
use App\Entity\Organization;
use App\Entity\Season;
use App\Entity\Team;
use App\Entity\User;
use App\Repository\FixtureRepository;
use App\Repository\NotificationRepository;
use App\Repository\OrganizationMembershipRepository;
use App\Tests\Factory\LeagueFactory;
use App\Tests\Factory\OrganizationFactory;
use App\Tests\Factory\SeasonFactory;
use App\Tests\Factory\SeasonTeamFactory;
use App\Tests\Factory\TeamFactory;
use App\Tests\Factory\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * "Which matches are about a day away" can only be tested with a clock somebody else owns.
 *
 * This is the class `ClockInterface` was introduced for in Stage 5, three stages before
 * anything used it: with the real clock, testing a twenty-four hour window means either
 * sleeping or writing fixtures whose kick-off is computed from the same `now()` the code
 * uses — which tests that two expressions agree, not that the window is right.
 */
final class KickOffReminderTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    private const NOW = '2026-05-01 12:00:00';

    private EntityManagerInterface $entityManager;
    private Organization $organization;
    private Season $season;
    private Team $home;
    private Team $away;
    private User $owner;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $this->owner = UserFactory::createOne();
        $this->organization = OrganizationFactory::createOne(['createdBy' => $this->owner]);
        $league = LeagueFactory::createOne(['organization' => $this->organization]);
        $this->season = SeasonFactory::createOne(['league' => $league, 'name' => '2026']);

        $this->home = TeamFactory::createOne(['organization' => $this->organization, 'name' => 'Stal']);
        $this->away = TeamFactory::createOne(['organization' => $this->organization, 'name' => 'Resovia']);
        SeasonTeamFactory::createOne(['season' => $this->season, 'team' => $this->home]);
        SeasonTeamFactory::createOne(['season' => $this->season, 'team' => $this->away]);
    }

    /**
     * The window is ±15 minutes around "in a day", matched to how often the schedule runs so
     * that every match falls into exactly one pass. These are its edges.
     */
    #[DataProvider('kickOffTimes')]
    public function testOnlyMatchesAboutADayAwayAreReminded(string $kickOff, bool $expected): void
    {
        $this->fixture($kickOff);

        $result = $this->reminder()->run();

        self::assertSame($expected ? 1 : 0, $result['matches'], $kickOff);
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function kickOffTimes(): iterable
    {
        yield 'exactly a day away' => ['2026-05-02 12:00:00', true];
        yield 'a day and ten minutes' => ['2026-05-02 12:10:00', true];
        yield 'a day less ten minutes' => ['2026-05-02 11:50:00', true];
        yield 'a day and twenty minutes' => ['2026-05-02 12:20:00', false];
        yield 'a day less twenty minutes' => ['2026-05-02 11:40:00', false];
        yield 'this evening' => ['2026-05-01 19:00:00', false];
        yield 'next week' => ['2026-05-08 12:00:00', false];
    }

    /**
     * A cancelled match keeps its kick-off time in the column, so filtering by time alone
     * would remind people about matches that are not going to be played.
     */
    #[DataProvider('ineligibleStatuses')]
    public function testOnlyScheduledMatchesAreReminded(MatchStatus $status): void
    {
        $fixture = $this->fixture('2026-05-02 12:00:00');
        $fixture->setStatus($status);
        $this->entityManager->flush();

        self::assertSame(0, $this->reminder()->run()['matches']);
    }

    /**
     * @return iterable<string, array{MatchStatus}>
     */
    public static function ineligibleStatuses(): iterable
    {
        yield 'being played' => [MatchStatus::Live];
        yield 'called off' => [MatchStatus::Cancelled];
        yield 'put back' => [MatchStatus::Postponed];
        yield 'already finished' => [MatchStatus::Finished];
    }

    /**
     * The schedule fires every fifteen minutes and a run that overlaps the previous one is
     * ordinary rather than exceptional. Nobody should be told twice.
     */
    public function testRunningTheScanTwiceRemindsNobodyTwice(): void
    {
        $this->fixture('2026-05-02 12:00:00');

        $first = $this->reminder()->run();
        $second = $this->reminder()->run();

        self::assertSame(1, $first['notifications']);
        self::assertSame(1, $second['matches'], 'the match is still in the window');
        self::assertSame(0, $second['notifications'], 'but everybody has already been told');

        self::assertSame(
            1,
            self::getContainer()->get(NotificationRepository::class)->unreadCount($this->owner),
        );
    }

    public function testAMatchWithNoKickOffTimeIsNeverReminded(): void
    {
        $this->fixture(null);

        self::assertSame(0, $this->reminder()->run()['matches']);
    }

    private function reminder(): KickOffReminder
    {
        $container = self::getContainer();

        return new KickOffReminder(
            $container->get(FixtureRepository::class),
            $container->get(OrganizationMembershipRepository::class),
            $container->get(Notifier::class),
            self::clock(),
        );
    }

    private static function clock(): ClockInterface
    {
        return new MockClock(new \DateTimeImmutable(self::NOW, new \DateTimeZone('UTC')));
    }

    private function fixture(?string $kickOff): Fixture
    {
        $fixture = new Fixture($this->season, $this->home, $this->away, 1, 1);

        if (null !== $kickOff) {
            $fixture->setKickOffAt(new \DateTimeImmutable($kickOff, new \DateTimeZone('UTC')));
        }

        $this->entityManager->persist($fixture);
        $this->entityManager->flush();

        return $fixture;
    }
}
