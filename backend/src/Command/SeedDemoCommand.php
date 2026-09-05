<?php

declare(strict_types=1);

namespace App\Command;

use App\Domain\Fixture\FixtureGenerator;
use App\Domain\Match\MatchEventRecorder;
use App\Domain\Match\MatchLifecycle;
use App\Domain\Organization\OrganizationManager;
use App\Domain\Squad\SquadManager;
use App\Entity\Fixture;
use App\Entity\League;
use App\Entity\MatchEventType;
use App\Entity\Organization;
use App\Entity\OrganizationRole;
use App\Entity\Player;
use App\Entity\PlayerPosition;
use App\Entity\Season;
use App\Entity\SeasonTeam;
use App\Entity\Team;
use App\Entity\User;
use App\Repository\OrganizationRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * A season worth looking at: twelve clubs, full squads, a generated calendar and thirteen
 * rounds already played.
 *
 * Two properties are worth more than the data itself.
 *
 * **It is deterministic.** The randomness comes from seeded `Mt19937` engines, so the same
 * command produces the same league every time, on every machine. A demo whose table changes
 * on each run cannot be used to check the table: there is nothing to compare against.
 *
 * **It plays through the real services.** Results are not written into the score columns.
 * Every match is started, its goals recorded one at a time and then finished, through the
 * same {@see MatchLifecycle} and {@see MatchEventRecorder} the API uses. That makes the seeder
 * an exercise of the domain rules rather than a way around them: a goal that stopped moving
 * the score, or a transition that stopped being allowed, breaks this command.
 */
#[AsCommand(
    name: 'app:seed:demo',
    description: 'Fill an empty database with a demonstration league.',
)]
final class SeedDemoCommand extends Command
{
    /**
     * The seed. Changing it changes every result in the demo, which is exactly why it is a
     * named constant and not a literal somewhere in the middle of the file.
     */
    private const SEED = 20260904;

    /**
     * The account the "try the demo" button signs in as.
     *
     * Deliberately **not** the owner. Everything interesting is open to an administrator —
     * creating leagues, registering clubs, running matches — but deleting the organization
     * needs OWNER, so a visitor cannot destroy the thing they came to look at. That is the
     * whole reason there are two accounts rather than one.
     */
    public const VISITOR_EMAIL = 'visitor@kickoff.test';

    private const ORGANIZATION = 'Kickoff Demo';
    private const ORGANIZATION_SLUG = 'demo';
    private const SEASON = '2026';

    private const LEAGUE = 'District League';
    private const LEAGUE_SLUG = 'district-league';
    private const LEAGUE_DESCRIPTION = 'Fourth tier of the Podkarpacie district. Twelve clubs, home and away, March to November.';

    /** Rounds played out of the twenty-two a twelve-club double round robin produces. */
    private const ROUNDS_PLAYED = 13;

    /**
     * Club name => the short name a league table has room to print.
     *
     * Written out rather than derived. Three letters chopped off the front and shouted —
     * STA, RES, KAR — is what an algorithm produces; "Stal", "Resovia", "Karpaty" is what a
     * person would write, and every fixture row and table line in the demo shows one.
     */
    private const CLUBS = [
        'Stal Rzeszów' => 'Stal',
        'Resovia' => 'Resovia',
        'Karpaty Krosno' => 'Karpaty',
        'Siarka Tarnobrzeg' => 'Siarka',
        'Sokół Sieniawa' => 'Sokół',
        'Wisłok Wiśniowa' => 'Wisłok',
        'Polonia Przemyśl' => 'Polonia',
        'Czarni Jasło' => 'Czarni',
        'Igloopol Dębica' => 'Igloopol',
        'San Sanok' => 'San',
        'Orzeł Przeworsk' => 'Orzeł',
        'Piast Tuczempy' => 'Piast',
    ];

    private const FIRST_NAMES = [
        'Jan', 'Piotr', 'Marcin', 'Tomasz', 'Łukasz', 'Kamil', 'Bartosz', 'Michał',
        'Adrian', 'Rafał', 'Dawid', 'Sebastian', 'Grzegorz', 'Mateusz', 'Paweł', 'Krzysztof',
        'Jakub', 'Filip', 'Wojciech', 'Maciej', 'Karol', 'Damian', 'Norbert', 'Przemysław',
    ];

    private const LAST_NAMES = [
        'Nowak', 'Kowalski', 'Wiśniewski', 'Wójcik', 'Kowalczyk', 'Kamiński', 'Lewandowski',
        'Zieliński', 'Szymański', 'Woźniak', 'Dąbrowski', 'Kozłowski', 'Jankowski', 'Mazur',
        'Kwiatkowski', 'Krawczyk', 'Piotrowski', 'Grabowski', 'Nowakowski', 'Pawłowski',
        'Michalski', 'Adamczyk', 'Dudek', 'Zając', 'Wieczorek', 'Jabłoński', 'Król',
        'Majewski', 'Olszewski', 'Jaworski', 'Wróbel', 'Malinowski', 'Pawlak', 'Witkowski',
        'Walczak', 'Stępień', 'Górski', 'Rutkowski', 'Michalak', 'Sikora',
    ];

    /**
     * Plausible age ranges, by position, for the season being seeded.
     *
     * Goalkeepers last longest and forwards start youngest — not a rule of the game, but it
     * is what a squad list looks like, and a column of ages that are all 24 reads as filler.
     *
     * @var array<string, array{int, int}>
     */
    private const AGE_BANDS = [
        PlayerPosition::Goalkeeper->value => [24, 38],
        PlayerPosition::Defender->value => [19, 35],
        PlayerPosition::Midfielder->value => [18, 34],
        PlayerPosition::Forward->value => [17, 32],
    ];

    /** The match engine: squad sizes, results, minutes, scorers. */
    private Randomizer $random;

    /**
     * Biography: names, dates of birth, who wears the armband.
     *
     * A second engine rather than more draws from the first, because `Mt19937` is a sequence
     * and inserting a draw shifts every draw after it. Drawing a date of birth from the match
     * engine would change every score in the demo, which is a strange thing for a birthday to
     * do — and a confusing diff to read six months later.
     *
     * This split does not make the *current* results stable retroactively: the names used to
     * be drawn from the match engine, so moving them here moved the scores once, on this
     * commit. Nothing depends on the old figures — the README's claim is that a given seed
     * produces the same league everywhere, not that it produces any particular league, and
     * that still holds. What the split buys is that the next change to a name list, an age
     * band or a card rate cannot move them again.
     */
    private Randomizer $people;

    /** Cards and substitutions, drawn after the score is settled, for the same reason. */
    private Randomizer $flavour;

    /**
     * Every first-name/surname pair, shuffled, drawn from without replacement.
     *
     * @var list<array{string, string}>
     */
    private array $nameSupply = [];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly OrganizationRepository $organizations,
        private readonly UserRepository $users,
        private readonly OrganizationManager $organizationManager,
        private readonly SquadManager $squads,
        private readonly FixtureGenerator $fixtures,
        private readonly MatchLifecycle $lifecycle,
        private readonly MatchEventRecorder $events,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly SluggerInterface $slugger,
    ) {
        parent::__construct();

        $this->random = new Randomizer(new Mt19937(self::SEED));
        $this->people = new Randomizer(new Mt19937(self::SEED + 1));
        $this->flavour = new Randomizer(new Mt19937(self::SEED + 2));
    }

    protected function configure(): void
    {
        $this
            ->addOption('flush', null, InputOption::VALUE_NONE, 'Delete the demo organization first and build it again.')
            ->addOption('owner-email', null, InputOption::VALUE_REQUIRED, 'The account that will own the demo league.', 'demo@kickoff.test')
            ->addOption(
                'owner-password',
                null,
                InputOption::VALUE_REQUIRED,
                'Password for that account. One is generated and printed if you do not supply it.',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var string $email */
        $email = $input->getOption('owner-email');
        $flush = (bool) $input->getOption('flush');

        $existing = $this->organizations->findOneBy(['slug' => self::ORGANIZATION_SLUG]);

        if (null !== $existing && !$flush) {
            // Running twice is a no-op rather than a second league beside the first. A seeder
            // that duplicates its own output cannot be put in a start-up script.
            $io->success(\sprintf('"%s" is already here. Pass --flush to build it again.', self::ORGANIZATION));

            return Command::SUCCESS;
        }

        if (null !== $existing) {
            $io->text('Removing the existing demo organization.');
            $this->organizationManager->delete($existing);
        }

        // The data is deterministic; the credential deliberately is not. A fixed password in
        // a public repository is a fixed password on every deployment that ever runs this.
        $password = $this->resolvePassword($input);

        $owner = $this->owner($email, $password);
        $organization = $this->organization($owner);
        $this->visitor($organization);
        $season = $this->season($organization);

        $io->section('Clubs and squads');
        $squads = $this->clubs($io, $organization, $season);

        $io->section('Calendar');
        $calendar = $this->fixtures->generate($season, doubleRound: true, firstRoundOn: $season->getStartDate(), daysBetweenRounds: 7);
        // `max()` on an empty array is fatal, and a season with too few clubs produces no
        // calendar at all. Unlikely here, but the guard costs a line and without it the crash
        // would come out of a progress message rather than out of anything that matters.
        $rounds = [] === $calendar
            ? 0
            : max(array_map(static fn (Fixture $f): int => $f->getRoundNumber(), $calendar));

        $io->text(\sprintf('%d fixtures across %d rounds.', \count($calendar), $rounds));

        $io->section('Results');
        $this->playSeason($io, $calendar, $squads);

        $io->success(\sprintf('"%s" is ready.', self::ORGANIZATION));
        $io->definitionList(
            ['Sign in as' => $email],
            ['Password' => $password],
            ['Visitor account' => self::VISITOR_EMAIL.' (administrator, no password)'],
            ['Rounds played' => self::ROUNDS_PLAYED],
        );

        return Command::SUCCESS;
    }

    private function resolvePassword(InputInterface $input): string
    {
        /** @var string|null $given */
        $given = $input->getOption('owner-password');

        if (null !== $given && '' !== $given) {
            return $given;
        }

        return bin2hex(random_bytes(8));
    }

    private function owner(string $email, string $password): User
    {
        $user = $this->users->findOneBy(['email' => User::normaliseEmail($email)]) ?? new User($email);

        $user->setFirstName('Demo');
        $user->setLastName('Owner');
        $user->setPassword($this->hasher->hashPassword($user, $password));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    /**
     * The visitor gets a password it will never use — the demo endpoint signs this account in
     * without one — but it gets a random one rather than a known or empty one, so that the
     * ordinary login form cannot be used to walk in through the front door.
     */
    private function visitor(Organization $organization): void
    {
        $existing = $this->users->findOneBy(['email' => User::normaliseEmail(self::VISITOR_EMAIL)]);
        $visitor = $existing ?? new User(self::VISITOR_EMAIL);

        $visitor->setFirstName('Demo');
        $visitor->setLastName('Visitor');
        $visitor->setPassword($this->hasher->hashPassword($visitor, bin2hex(random_bytes(16))));

        $this->entityManager->persist($visitor);
        $this->entityManager->flush();

        $this->organizationManager->addMember($organization, self::VISITOR_EMAIL, OrganizationRole::Admin);
    }

    private function organization(User $owner): Organization
    {
        $membership = $this->organizationManager->create($owner, self::ORGANIZATION, self::ORGANIZATION_SLUG);

        return $membership->getOrganization();
    }

    private function season(Organization $organization): Season
    {
        $league = new League($organization, self::LEAGUE, self::LEAGUE_SLUG);
        $league->setDescription(self::LEAGUE_DESCRIPTION);

        $season = new Season($league, self::SEASON, new \DateTimeImmutable('2026-03-01'));
        $season->setEndDate(new \DateTimeImmutable('2026-11-30'));

        $this->entityManager->persist($league);
        $this->entityManager->persist($season);
        $this->entityManager->flush();

        return $season;
    }

    /**
     * @return array<int, list<Player>> team id => the players available to it
     */
    private function clubs(SymfonyStyle $io, Organization $organization, Season $season): array
    {
        $squads = [];
        $io->progressStart(\count(self::CLUBS));

        foreach (self::CLUBS as $name => $shortName) {
            $team = new Team($organization, $name, $this->slugger->slug($name)->lower()->toString());
            $team->setShortName($shortName);
            $this->entityManager->persist($team);
            $this->entityManager->flush();

            $seasonTeam = $this->squads->registerTeam($season, $team);
            $squads[(int) $team->getId()] = $this->squad($organization, $seasonTeam);

            // Flushed a club at a time rather than all twelve at the end. At this size it
            // changes nothing; the habit matters when a seeder grows to tens of thousands of
            // rows, where one unit of work holds every entity in memory at once.
            //
            // `EntityManager::clear()` would be the other half of that habit and is
            // deliberately not called here: the squads collected above have to stay managed
            // for the matches below, and detaching them to save memory the command does not
            // need would trade a real bug for an imaginary saving.
            $this->entityManager->flush();
            $io->progressAdvance();
        }

        $io->progressFinish();
        $io->text(\sprintf('%d clubs registered.', \count($squads)));

        return $squads;
    }

    /**
     * @return list<Player>
     */
    private function squad(Organization $organization, SeasonTeam $seasonTeam): array
    {
        $size = $this->random->getInt(16, 18);
        $seasonStart = $seasonTeam->getSeason()->getStartDate();
        $players = [];

        // Twelve goalkeeper-captains across twelve clubs is a tell that the data was
        // generated. The armband goes to an outfielder with a senior number instead.
        $captainShirt = $this->people->getInt(2, min(11, $size));

        for ($shirt = 1; $shirt <= $size; ++$shirt) {
            $position = $this->position($shirt);
            [$firstName, $lastName] = $this->nextName();

            $player = new Player($organization, $firstName, $lastName);
            $player->setDateOfBirth($this->birthDate($position, $seasonStart));

            $this->entityManager->persist($player);
            $this->entityManager->flush();

            $this->squads->addToSquad(
                $seasonTeam,
                $player,
                $shirt,
                $position,
                captain: $shirt === $captainShirt,
            );

            $players[] = $player;
        }

        return $players;
    }

    private function position(int $shirt): PlayerPosition
    {
        return match (true) {
            1 === $shirt => PlayerPosition::Goalkeeper,
            $shirt <= 6 => PlayerPosition::Defender,
            $shirt <= 12 => PlayerPosition::Midfielder,
            default => PlayerPosition::Forward,
        };
    }

    /**
     * A date of birth that makes the player the right age *for this season*.
     *
     * Anchored on the season's start rather than on today, which is what keeps it
     * deterministic: anchoring on `now()` would produce a different database every day, and
     * eventually players born after the season they played in.
     *
     * The age a client displays is still computed against the real today and so grows over
     * time. That is correct — people do get older — and it is why the date is what gets
     * stored rather than the age.
     */
    private function birthDate(PlayerPosition $position, \DateTimeImmutable $seasonStart): \DateTimeImmutable
    {
        [$youngest, $oldest] = self::AGE_BANDS[$position->value];

        $years = $this->people->getInt($youngest, $oldest);
        $days = $this->people->getInt(0, 364);

        return $seasonStart->modify(\sprintf('-%d years -%d days', $years, $days));
    }

    /**
     * A name nobody else in this demo has.
     *
     * Twenty-four first names against forty surnames is 960 pairs for about two hundred
     * players, and drawing them at random would still collide constantly — the birthday
     * paradox does not care how big the pool is. Three players called Jan Kowalski in a
     * demonstration database reads as a bug in the seeder, which is exactly what it is.
     *
     * Shuffled once and popped from, so the supply runs out loudly rather than quietly
     * repeating itself the day somebody asks for five hundred players.
     *
     * @return array{string, string}
     */
    private function nextName(): array
    {
        if ([] === $this->nameSupply) {
            $pairs = [];

            foreach (self::FIRST_NAMES as $firstName) {
                foreach (self::LAST_NAMES as $lastName) {
                    $pairs[] = [$firstName, $lastName];
                }
            }

            /** @var list<array{string, string}> $shuffled */
            $shuffled = $this->people->shuffleArray($pairs);
            $this->nameSupply = $shuffled;
        }

        $name = array_pop($this->nameSupply);

        if (null === $name) {
            throw new \LogicException('The demo has run out of distinct names. Widen FIRST_NAMES or LAST_NAMES.');
        }

        return $name;
    }

    /**
     * @param list<Fixture>            $calendar
     * @param array<int, list<Player>> $squads
     */
    private function playSeason(SymfonyStyle $io, array $calendar, array $squads): void
    {
        $played = array_values(array_filter(
            $calendar,
            static fn (Fixture $f): bool => $f->getRoundNumber() <= self::ROUNDS_PLAYED,
        ));

        // The odd states are picked by position in the list rather than at random, so that
        // "the demo has a live match" is a fact about the seeder and not a coin toss.
        $cancelled = $played[2] ?? null;
        $postponed = [$played[5] ?? null, $played[9] ?? null];
        $live = $played[\count($played) - 1] ?? null;

        $io->progressStart(\count($played));

        foreach ($played as $fixture) {
            if ($fixture === $cancelled) {
                $this->lifecycle->cancel($fixture);
            } elseif (\in_array($fixture, $postponed, true)) {
                $this->lifecycle->postpone($fixture);
            } else {
                $this->playMatch($fixture, $squads, leaveRunning: $fixture === $live);
            }

            $io->progressAdvance();
        }

        $io->progressFinish();
        $io->text(\sprintf(
            '%d matches played, 1 cancelled, 2 postponed, 1 still running.',
            \count($played) - 4,
        ));
    }

    /**
     * @param array<int, list<Player>> $squads
     */
    private function playMatch(Fixture $fixture, array $squads, bool $leaveRunning): void
    {
        $this->lifecycle->start($fixture);

        $minutes = [];
        $home = $fixture->getHomeTeam();
        $away = $fixture->getAwayTeam();

        // Home advantage, and a distribution that looks like football rather than like a dice
        // roll: nil and one are common, five is not.
        foreach ([[$home, 0.55], [$away, 0.45]] as [$team, $weight]) {
            $goals = $this->goalCount($weight);

            for ($i = 0; $i < $goals; ++$i) {
                $this->events->record(
                    $fixture,
                    MatchEventType::Goal,
                    $this->uniqueMinute($minutes, $this->random),
                    $team,
                    $this->scorer($squads[(int) $team->getId()]),
                );
            }
        }

        foreach ([$home, $away] as $team) {
            if ($this->fraction($this->random) < 0.35) {
                $this->events->record(
                    $fixture,
                    MatchEventType::YellowCard,
                    $this->uniqueMinute($minutes, $this->random),
                    $team,
                    $squads[(int) $team->getId()][$this->random->getInt(0, \count($squads[(int) $team->getId()]) - 1)],
                );
            }
        }

        // Everything the score depends on has now been recorded. What follows draws from a
        // different engine and is appended afterwards, so no draw above can be shifted by it
        // — including the retries inside uniqueMinute(), which is the subtle half of that.
        $this->addColour($fixture, $squads);

        if (!$leaveRunning) {
            $this->lifecycle->finish($fixture);
        }
    }

    /**
     * The events that do not change the score but do make a match look like one.
     *
     * Without these the statistics page has a permanently empty "Red" column and the match
     * timeline has never once shown a substitution — two features that work, demonstrated by
     * data that never exercises them.
     *
     * @param array<int, list<Player>> $squads
     */
    private function addColour(Fixture $fixture, array $squads): void
    {
        $minutes = [];

        foreach ([$fixture->getHomeTeam(), $fixture->getAwayTeam()] as $team) {
            $squad = $squads[(int) $team->getId()];

            if ($this->fraction($this->flavour) < 0.06) {
                $this->events->record(
                    $fixture,
                    MatchEventType::RedCard,
                    $this->uniqueMinute($minutes, $this->flavour),
                    $team,
                    $squad[$this->flavour->getInt(0, \count($squad) - 1)],
                );
            }

            // Two or three changes, the ones coming on drawn from the back of the squad —
            // the list is ordered by shirt number, so that is the bench.
            $bench = array_slice($squad, 11);

            for ($i = 0; $i < $this->flavour->getInt(2, 3) && [] !== $bench; ++$i) {
                $comingOn = array_splice($bench, $this->flavour->getInt(0, \count($bench) - 1), 1)[0];
                $goingOff = $squad[$this->flavour->getInt(0, min(10, \count($squad) - 1))];

                $this->events->record(
                    $fixture,
                    MatchEventType::Substitution,
                    $this->uniqueMinute($minutes, $this->flavour, from: 55),
                    $team,
                    $goingOff,
                    $comingOn,
                );
            }
        }
    }

    /**
     * `Randomizer::getFloat()` arrived in PHP 8.3 and this project targets 8.2, so the
     * fraction is built from an integer draw. Same determinism, whichever engine is asked.
     */
    private function fraction(Randomizer $random): float
    {
        return $random->getInt(0, 9999) / 10000;
    }

    private function goalCount(float $homeWeight): int
    {
        $roll = $this->fraction($this->random) * $homeWeight * 2;

        return match (true) {
            $roll < 0.30 => 0,
            $roll < 0.62 => 1,
            $roll < 0.84 => 2,
            $roll < 0.95 => 3,
            default => 4,
        };
    }

    /**
     * Forwards score more than goalkeepers. The squad is ordered by shirt number, so the back
     * of it is the front of the team.
     *
     * @param list<Player> $squad
     */
    private function scorer(array $squad): Player
    {
        $from = min(6, \count($squad) - 1);

        return $squad[$this->random->getInt($from, \count($squad) - 1)];
    }

    /**
     * @param list<int> $taken
     */
    private function uniqueMinute(array &$taken, Randomizer $random, int $from = 1): int
    {
        do {
            $minute = $random->getInt($from, 90);
        } while (\in_array($minute, $taken, true));

        $taken[] = $minute;

        return $minute;
    }
}
