<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Player;
use App\Entity\RosterEntry;
use App\Entity\SeasonTeam;
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

    public function currentCaptain(SeasonTeam $seasonTeam): ?RosterEntry
    {
        return $this->findOneBy(['seasonTeam' => $seasonTeam, 'captain' => true]);
    }
}
