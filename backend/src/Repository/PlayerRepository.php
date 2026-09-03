<?php

declare(strict_types=1);

namespace App\Repository;

use App\Dto\Input\ListQuery;
use App\Entity\Organization;
use App\Entity\Player;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Player>
 */
class PlayerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Player::class);
    }

    public function scopedQuery(Organization $organization, ListQuery $query): QueryBuilder
    {
        $qb = $this->createQueryBuilder('p')
            ->where('p.organization = :organization')
            ->setParameter('organization', $organization);

        if (null !== $term = $query->searchTerm()) {
            // CONCAT so that "Jan Kowalski" finds the person whose first and last name are
            // stored separately. Searching each column alone would not match a full name,
            // which is exactly what somebody types.
            $qb->andWhere("p.firstName LIKE :search OR p.lastName LIKE :search OR CONCAT(p.firstName, ' ', p.lastName) LIKE :search")
                ->setParameter('search', '%'.$term.'%');
        }

        return $qb;
    }

    public function findOneInOrganization(Organization $organization, int $playerId): ?Player
    {
        return $this->findOneBy(['organization' => $organization, 'id' => $playerId]);
    }
}
