<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Player;
use App\Entity\RosterEntry;
use App\Entity\Season;
use App\Entity\SeasonTeam;
use App\Entity\Team;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RosterEntry>
 */
class RosterEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RosterEntry::class);
    }

    /**
     * @return list<RosterEntry>
     */
    public function findForSquad(SeasonTeam $seasonTeam): array
    {
        /* @var list<RosterEntry> */
        return $this->createQueryBuilder('r')
            ->addSelect('p')
            ->innerJoin('r.player', 'p')
            ->where('r.seasonTeam = :seasonTeam')
            // Numbered players first and in order, then the unnumbered ones alphabetically:
            // NULLs sort last in MariaDB only with this expression, not by default.
            ->orderBy('CASE WHEN r.shirtNumber IS NULL THEN 1 ELSE 0 END', 'ASC')
            ->addOrderBy('r.shirtNumber', 'ASC')
            ->addOrderBy('p.lastName', 'ASC')
            ->addOrderBy('r.id', 'ASC')
            ->setParameter('seasonTeam', $seasonTeam)
            ->getQuery()
            ->getResult();
    }

    public function findOneInSquad(SeasonTeam $seasonTeam, int $rosterEntryId): ?RosterEntry
    {
        return $this->findOneBy(['seasonTeam' => $seasonTeam, 'id' => $rosterEntryId]);
    }

    public function playerIsInSquad(SeasonTeam $seasonTeam, Player $player): bool
    {
        return null !== $this->findOneBy(['seasonTeam' => $seasonTeam, 'player' => $player]);
    }

    public function shirtNumberTaken(SeasonTeam $seasonTeam, int $shirtNumber, ?int $exceptId = null): bool
    {
        $qb = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.seasonTeam = :seasonTeam')
            ->andWhere('r.shirtNumber = :shirtNumber')
            ->setParameter('seasonTeam', $seasonTeam)
            ->setParameter('shirtNumber', $shirtNumber);

        if (null !== $exceptId) {
            $qb->andWhere('r.id <> :exceptId')->setParameter('exceptId', $exceptId);
        }

        return ((int) $qb->getQuery()->getSingleScalarResult()) > 0;
    }

    /**
     * Is this player in that club's squad, for that season?
     *
     * The season is part of the question and not an afterthought: a player who turned out for
     * Stal last year is not eligible for them this year unless somebody registered him again.
     */
    public function isRosteredFor(Season $season, Team $team, Player $player): bool
    {
        $count = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->innerJoin('r.seasonTeam', 'st')
            ->where('r.player = :player')
            ->andWhere('st.season = :season')
            ->andWhere('st.team = :team')
            ->setParameter('player', $player)
            ->setParameter('season', $season)
            ->setParameter('team', $team)
            ->getQuery()
            ->getSingleScalarResult();

        return ((int) $count) > 0;
    }

    public function currentCaptain(SeasonTeam $seasonTeam): ?RosterEntry
    {
        return $this->findOneBy(['seasonTeam' => $seasonTeam, 'captain' => true]);
    }

    /**
     * Each player's *current* squad entry: the one from the most recent season they appear in.
     *
     * Ordered newest season first and folded in PHP rather than asked for with a window
     * function, which DQL does not have. One query for the whole page, whatever its size —
     * the alternative is a query per player, which is the entire reason this method exists
     * rather than a getter on the entity.
     *
     * Entities are fetch-joined rather than scalars selected, because the caller needs the
     * position enum and the season's start date, and both of those are values Doctrine
     * converts on hydration.
     *
     * @param list<int> $playerIds
     *
     * @return array<int, RosterEntry> player id => their latest entry
     */
    public function currentForPlayers(array $playerIds): array
    {
        if ([] === $playerIds) {
            return [];
        }

        /** @var list<RosterEntry> $rows */
        $rows = $this->createQueryBuilder('r')
            ->addSelect('st', 's', 'l', 't')
            ->innerJoin('r.seasonTeam', 'st')
            ->innerJoin('st.season', 's')
            ->innerJoin('s.league', 'l')
            ->innerJoin('st.team', 't')
            ->where('IDENTITY(r.player) IN (:players)')
            ->orderBy('s.startDate', 'DESC')
            ->addOrderBy('s.id', 'DESC')
            ->addOrderBy('r.id', 'DESC')
            ->setParameter('players', $playerIds)
            ->getQuery()
            ->getResult();

        $current = [];

        foreach ($rows as $entry) {
            // First one wins: the rows arrive newest first, so this is "most recent season"
            // without a second pass and without a comparison that could disagree with the
            // ORDER BY above.
            $current[(int) $entry->getPlayer()->getId()] ??= $entry;
        }

        return $current;
    }

    /**
     * One player's whole career, newest season first.
     *
     * The same joins as {@see currentForPlayers()} and for the same reason, but unfolded:
     * the profile page wants every entry, not the latest one.
     *
     * @return list<RosterEntry>
     */
    public function findForPlayer(Player $player): array
    {
        /* @var list<RosterEntry> */
        return $this->createQueryBuilder('r')
            ->addSelect('st', 's', 'l', 't')
            ->innerJoin('r.seasonTeam', 'st')
            ->innerJoin('st.season', 's')
            ->innerJoin('s.league', 'l')
            ->innerJoin('st.team', 't')
            ->where('r.player = :player')
            ->orderBy('s.startDate', 'DESC')
            ->addOrderBy('s.id', 'DESC')
            ->addOrderBy('r.id', 'DESC')
            ->setParameter('player', $player)
            ->getQuery()
            ->getResult();
    }
}
