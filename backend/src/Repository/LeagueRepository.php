<?php

declare(strict_types=1);

namespace App\Repository;

use App\Dto\Input\ListQuery;
use App\Entity\League;
use App\Entity\Organization;
use App\Entity\OrganizationRole;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<League>
 */
class LeagueRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, League::class);
    }

    public function scopedQuery(Organization $organization, ListQuery $query): QueryBuilder
    {
        $qb = $this->createQueryBuilder('l')
            ->where('l.organization = :organization')
            ->setParameter('organization', $organization);

        if (null !== $term = $query->searchTerm()) {
            // LOWER on both sides: PostgreSQL's LIKE is case-sensitive, unlike MySQL's
            // under a *_ci collation, which made this work by accident until the move.
            $qb->andWhere('LOWER(l.name) LIKE :search OR LOWER(l.slug) LIKE :search')
                ->setParameter('search', '%'.mb_strtolower($term).'%');
        }

        return $qb;
    }

    public function findOneInOrganization(Organization $organization, int $leagueId): ?League
    {
        return $this->findOneBy(['organization' => $organization, 'id' => $leagueId]);
    }

    /**
     * The league, its organization and the caller's role there — in one query.
     *
     * Three questions answered at once: does the league exist, does it belong to *this*
     * organization, and is the caller a member of it. Asking them separately would mean
     * three round trips and three chances to forget one; asking them together means a league
     * reached through the wrong organization is simply a missing row, exactly like a league
     * that was never created.
     *
     * @return array{0: League, role: OrganizationRole}|null
     */
    public function findScoped(User $user, int $organizationId, int $leagueId): ?array
    {
        /* @var array{0: League, role: OrganizationRole}|null */
        return $this->createQueryBuilder('l')
            ->select('l', 'm.role AS role')
            ->innerJoin('l.organization', 'o')
            ->innerJoin('o.memberships', 'm', 'WITH', 'm.user = :user')
            ->where('l.id = :leagueId')
            ->andWhere('o.id = :organizationId')
            ->setParameter('user', $user)
            ->setParameter('leagueId', $leagueId)
            ->setParameter('organizationId', $organizationId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function slugExists(Organization $organization, string $slug): bool
    {
        return null !== $this->findOneBy(['organization' => $organization, 'slug' => $slug]);
    }
}
