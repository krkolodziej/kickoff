<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\OrganizationRole;
use App\Entity\RosterEntry;
use App\Entity\Season;
use App\Entity\SeasonTeam;
use App\Entity\Team;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SeasonTeam>
 */
class SeasonTeamRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SeasonTeam::class);
    }

    /**
     * The clubs registered for a season, each with its squad size.
     *
     * Two queries, whatever the number of clubs. Both halves matter:
     *
     * `addSelect('t')` stops the club itself being a lazy proxy that the response builder
     * wakes one row at a time. Less obviously, so does the separate count: calling
     * `$seasonTeam->getRosterEntries()->count()` on an uninitialised collection **loads the
     * whole collection**, so twelve clubs would be twelve extra queries — and every one of
     * them would return data nobody wanted, only to have it counted and thrown away.
     *
     * A test asserts that the query count does not change with the number of clubs, which is
     * a stronger claim than any particular number.
     *
     * @return list<array{seasonTeam: SeasonTeam, squadSize: int}>
     */
    public function findForSeason(Season $season): array
    {
        /** @var list<SeasonTeam> $seasonTeams */
        $seasonTeams = $this->createQueryBuilder('st')
            ->addSelect('t')
            ->innerJoin('st.team', 't')
            ->where('st.season = :season')
            ->orderBy('t.name', 'ASC')
            ->addOrderBy('st.id', 'ASC')
            ->setParameter('season', $season)
            ->getQuery()
            ->getResult();

        if ([] === $seasonTeams) {
            return [];
        }

        // Grouped separately rather than as an aggregate beside the entity select: mixing
        // COUNT() with hydrated entities needs a GROUP BY over every selected column, which
        // is where ONLY_FULL_GROUP_BY differences between MySQL and MariaDB start to bite.
        /** @var list<array{seasonTeamId: int, squadSize: int|string}> $counts */
        $counts = $this->getEntityManager()->createQueryBuilder()
            ->select('IDENTITY(r.seasonTeam) AS seasonTeamId', 'COUNT(r.id) AS squadSize')
            ->from(RosterEntry::class, 'r')
            ->where('r.seasonTeam IN (:seasonTeams)')
            ->groupBy('r.seasonTeam')
            ->setParameter('seasonTeams', $seasonTeams)
            ->getQuery()
            ->getResult();

        $sizes = [];

        foreach ($counts as $row) {
            $sizes[(int) $row['seasonTeamId']] = (int) $row['squadSize'];
        }

        return array_map(
            static fn (SeasonTeam $seasonTeam): array => [
                'seasonTeam' => $seasonTeam,
                'squadSize' => $sizes[(int) $seasonTeam->getId()] ?? 0,
            ],
            $seasonTeams,
        );
    }

    /**
     * @return array{0: SeasonTeam, role: OrganizationRole}|null
     */
    public function findScoped(User $user, int $organizationId, int $leagueId, int $seasonId, int $seasonTeamId): ?array
    {
        /* @var array{0: SeasonTeam, role: OrganizationRole}|null */
        return $this->createQueryBuilder('st')
            ->select('st', 't', 's', 'l', 'm.role AS role')
            ->innerJoin('st.team', 't')
            ->innerJoin('st.season', 's')
            ->innerJoin('s.league', 'l')
            ->innerJoin('l.organization', 'o')
            ->innerJoin('o.memberships', 'm', 'WITH', 'm.user = :user')
            ->where('st.id = :seasonTeamId')
            ->andWhere('s.id = :seasonId')
            ->andWhere('l.id = :leagueId')
            ->andWhere('o.id = :organizationId')
            ->setParameter('user', $user)
            ->setParameter('seasonTeamId', $seasonTeamId)
            ->setParameter('seasonId', $seasonId)
            ->setParameter('leagueId', $leagueId)
            ->setParameter('organizationId', $organizationId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function isRegistered(Season $season, Team $team): bool
    {
        return null !== $this->findOneBy(['season' => $season, 'team' => $team]);
    }
}
