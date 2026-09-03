<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Fixture;
use App\Entity\Season;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Fixture>
 */
class FixtureRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Fixture::class);
    }

    /**
     * A season's calendar, with both clubs loaded.
     *
     * `addSelect` on both sides, because a 132-fixture list that lazy-loads two clubs per row
     * is 265 queries — the textbook N+1, and the reason the query-count test from the last
     * stage exists.
     *
     * @return list<Fixture>
     */
    public function findForSeason(Season $season, ?int $round = null, ?int $teamId = null): array
    {
        $qb = $this->createQueryBuilder('f')
            ->addSelect('home', 'away')
            ->innerJoin('f.homeTeam', 'home')
            ->innerJoin('f.awayTeam', 'away')
            ->where('f.season = :season')
            ->orderBy('f.roundNumber', 'ASC')
            ->addOrderBy('f.leg', 'ASC')
            ->addOrderBy('home.name', 'ASC')
            ->addOrderBy('f.id', 'ASC')
            ->setParameter('season', $season);

        if (null !== $round) {
            $qb->andWhere('f.roundNumber = :round')->setParameter('round', $round);
        }

        if (null !== $teamId) {
            // Home *or* away: "show me Resovia's season" means both.
            $qb->andWhere('home.id = :teamId OR away.id = :teamId')->setParameter('teamId', $teamId);
        }

        /* @var list<Fixture> */
        return $qb->getQuery()->getResult();
    }

    public function seasonHasFixtures(Season $season): bool
    {
        $count = $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->where('f.season = :season')
            ->setParameter('season', $season)
            ->getQuery()
            ->getSingleScalarResult();

        return ((int) $count) > 0;
    }

    public function deleteForSeason(Season $season): int
    {
        return (int) $this->createQueryBuilder('f')
            ->delete()
            ->where('f.season = :season')
            ->setParameter('season', $season)
            ->getQuery()
            ->execute();
    }
}
