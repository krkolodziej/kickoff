<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Domain\Match\MatchLifecycle;
use App\Entity\Fixture;
use App\Entity\MatchStatus;
use App\Tests\Factory\LeagueFactory;
use App\Tests\Factory\OrganizationFactory;
use App\Tests\Factory\SeasonFactory;
use App\Tests\Factory\SeasonTeamFactory;
use App\Tests\Factory\TeamFactory;
use App\Tests\Factory\UserFactory;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * The claim this file exists to check: **a message sent through the Doctrine transport is part
 * of the transaction that sent it.**.
 *
 * That is the reason this application queues through its own database instead of Redis, and
 * it is the sort of claim that is easy to write in a comment and never verify. So it is
 * verified from both directions — commit puts a row there, rollback takes it away — by
 * reading `messenger_messages` directly rather than by asking Messenger, which would only
 * report what it believes it did.
 */
final class MessengerTransactionTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    private EntityManagerInterface $entityManager;
    private Connection $connection;
    private MatchLifecycle $lifecycle;

    protected function setUp(): void
    {
        self::bootKernel();

        $container = self::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->connection = $this->entityManager->getConnection();
        $this->lifecycle = $container->get(MatchLifecycle::class);
    }

    public function testFinishingAMatchQueuesAMessage(): void
    {
        $fixture = $this->fixture();

        self::assertSame(0, $this->queued());

        $this->lifecycle->start($fixture);
        self::assertSame(0, $this->queued(), 'starting a match announces nothing');

        $this->lifecycle->finish($fixture);
        self::assertSame(1, $this->queued());
    }

    /**
     * The half that matters.
     *
     * The whole transition runs inside a transaction that is then rolled back. If the message
     * were sent to a broker outside the database it would already be gone — someone would be
     * told about a result the database does not have. Because the transport is a table on the
     * same connection, the row leaves with everything else.
     *
     * The test's own outer transaction (opened by dama for isolation) means this rollback is
     * really a savepoint, which is exactly the nesting the application does anyway.
     */
    public function testARolledBackResultTakesItsMessageWithIt(): void
    {
        $fixture = $this->fixture();
        $this->lifecycle->start($fixture);

        $before = $this->queued();

        // No assertion that the exception escaped: PHPStan can see that the closure always
        // throws, so such an assertion could not fail and would prove nothing. What is worth
        // checking is what the rollback left behind, below.
        try {
            $this->entityManager->wrapInTransaction(function () use ($fixture): void {
                $this->lifecycle->finish($fixture);

                // Anything at all going wrong after the result is recorded: a constraint, a
                // dropped connection, a bug three lines further down.
                throw new \RuntimeException('something went wrong afterwards');
            });
        } catch (\RuntimeException) {
            // expected
        }

        self::assertSame($before, $this->queued(), 'the message rolled back with the result');

        $this->entityManager->clear();
        $reloaded = $this->entityManager->find(Fixture::class, $fixture->getId());
        self::assertNotNull($reloaded);
        self::assertSame(
            MatchStatus::Live,
            $reloaded->getStatus(),
            'and the result itself is gone too, which is the other half of the guarantee',
        );
    }

    private function queued(): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM messenger_messages WHERE queue_name = :queue',
            ['queue' => 'async'],
        );
    }

    private function fixture(): Fixture
    {
        $owner = UserFactory::createOne();
        $organization = OrganizationFactory::createOne(['createdBy' => $owner]);
        $league = LeagueFactory::createOne(['organization' => $organization]);
        $season = SeasonFactory::createOne(['league' => $league, 'name' => '2026']);

        $home = TeamFactory::createOne(['organization' => $organization, 'name' => 'Stal']);
        $away = TeamFactory::createOne(['organization' => $organization, 'name' => 'Resovia']);
        SeasonTeamFactory::createOne(['season' => $season, 'team' => $home]);
        SeasonTeamFactory::createOne(['season' => $season, 'team' => $away]);

        $fixture = new Fixture($season, $home, $away, 1, 1);
        $this->entityManager->persist($fixture);
        $this->entityManager->flush();

        return $fixture;
    }
}
