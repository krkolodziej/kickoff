<?php

declare(strict_types=1);

namespace App\Repository;

use App\Dto\Input\ListQuery;
use App\Entity\Organization;
use App\Entity\Team;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Team>
 */
class TeamRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Team::class);
    }

    public function scopedQuery(Organization $organization, ListQuery $query): QueryBuilder
    {
        $qb = $this->createQueryBuilder('t')
            ->where('t.organization = :organization')
            ->setParameter('organization', $organization);

        if (null !== $term = $query->searchTerm()) {
            $qb->andWhere('t.name LIKE :search OR t.shortName LIKE :search OR t.slug LIKE :search')
                ->setParameter('search', '%'.$term.'%');
        }

        return $qb;
    }

    public function findOneInOrganization(Organization $organization, int $teamId): ?Team
    {
        return $this->findOneBy(['organization' => $organization, 'id' => $teamId]);
    }

    public function slugExists(Organization $organization, string $slug): bool
    {
        return null !== $this->findOneBy(['organization' => $organization, 'slug' => $slug]);
    }
}
