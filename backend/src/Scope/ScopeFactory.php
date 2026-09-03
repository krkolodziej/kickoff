<?php

declare(strict_types=1);

namespace App\Scope;

use App\Entity\User;
use App\Repository\FixtureRepository;
use App\Repository\LeagueRepository;
use App\Repository\OrganizationMembershipRepository;
use App\Repository\SeasonRepository;
use App\Repository\SeasonTeamRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Turns route parameters into a proven scope, or into a 404.
 *
 * The single most important line in this class is the one that is missing: there is no
 * branch that says "the organization exists but you are not in it". A caller who is not a
 * member gets exactly the same answer as one who asked for an id that was never issued.
 * Answering 403 there would confirm the organization exists, which is information the
 * caller has not earned.
 */
final class ScopeFactory
{
    public function __construct(
        private readonly OrganizationMembershipRepository $memberships,
        private readonly LeagueRepository $leagues,
        private readonly SeasonRepository $seasons,
        private readonly SeasonTeamRepository $seasonTeams,
        private readonly FixtureRepository $fixtures,
    ) {
    }

    public function organizationScope(User $user, int $organizationId): OrganizationScope
    {
        $membership = $this->memberships->findForUserAndOrganization($user, $organizationId);

        if (null === $membership) {
            throw new NotFoundHttpException();
        }

        return new OrganizationScope($membership->getOrganization(), $membership->getRole());
    }

    public function leagueScope(User $user, int $organizationId, int $leagueId): LeagueScope
    {
        $row = $this->leagues->findScoped($user, $organizationId, $leagueId);

        if (null === $row) {
            throw new NotFoundHttpException();
        }

        $league = $row[0];

        return new LeagueScope(
            new OrganizationScope($league->getOrganization(), $row['role']),
            $league,
        );
    }

    public function seasonScope(User $user, int $organizationId, int $leagueId, int $seasonId): SeasonScope
    {
        $row = $this->seasons->findScoped($user, $organizationId, $leagueId, $seasonId);

        if (null === $row) {
            throw new NotFoundHttpException();
        }

        $season = $row[0];
        $league = $season->getLeague();

        return new SeasonScope(
            new LeagueScope(new OrganizationScope($league->getOrganization(), $row['role']), $league),
            $season,
        );
    }

    public function seasonTeamScope(
        User $user,
        int $organizationId,
        int $leagueId,
        int $seasonId,
        int $seasonTeamId,
    ): SeasonTeamScope {
        $row = $this->seasonTeams->findScoped($user, $organizationId, $leagueId, $seasonId, $seasonTeamId);

        if (null === $row) {
            throw new NotFoundHttpException();
        }

        $seasonTeam = $row[0];
        $season = $seasonTeam->getSeason();
        $league = $season->getLeague();

        return new SeasonTeamScope(
            new SeasonScope(
                new LeagueScope(new OrganizationScope($league->getOrganization(), $row['role']), $league),
                $season,
            ),
            $seasonTeam,
        );
    }

    public function fixtureScope(
        User $user,
        int $organizationId,
        int $leagueId,
        int $seasonId,
        int $fixtureId,
    ): FixtureScope {
        $row = $this->fixtures->findScoped($user, $organizationId, $leagueId, $seasonId, $fixtureId);

        if (null === $row) {
            throw new NotFoundHttpException();
        }

        $fixture = $row[0];
        $season = $fixture->getSeason();
        $league = $season->getLeague();

        return new FixtureScope(
            new SeasonScope(
                new LeagueScope(new OrganizationScope($league->getOrganization(), $row['role']), $league),
                $season,
            ),
            $fixture,
        );
    }
}
