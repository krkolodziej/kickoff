<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Fixture;
use App\Entity\MatchEvent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MatchEvent>
 */
class MatchEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MatchEvent::class);
    }

    /**
     * The timeline, in the order it happened.
     *
     * `addSelect` on the player, the club and the substitute: a timeline renders every one of
     * those names, and without it a fifteen-event match is fifty queries.
     *
     * The tiebreaker on id matters more than usual here — two goals in the same minute are
     * common, and their order in the list should at least be stable between refreshes.
     *
     * @return list<MatchEvent>
     */
    public function findForFixture(Fixture $fixture): array
    {
        /* @var list<MatchEvent> */
        return $this->createQueryBuilder('e')
            ->addSelect('p', 't', 'rp')
            ->innerJoin('e.player', 'p')
            ->innerJoin('e.team', 't')
            ->leftJoin('e.relatedPlayer', 'rp')
            ->where('e.fixture = :fixture')
            ->orderBy('e.minute', 'ASC')
            ->addOrderBy('e.id', 'ASC')
            ->setParameter('fixture', $fixture)
            ->getQuery()
            ->getResult();
    }
}
