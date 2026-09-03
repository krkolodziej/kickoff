<?php

declare(strict_types=1);

namespace App\Scope;

use App\Entity\League;
use App\Entity\Organization;
use App\Entity\OrganizationRole;
use App\Entity\Season;

final readonly class SeasonScope implements ScopeInterface
{
    public function __construct(
        private LeagueScope $leagueScope,
        private Season $season,
    ) {
    }

    public function organization(): Organization
    {
        return $this->leagueScope->organization();
    }

    public function role(): OrganizationRole
    {
        return $this->leagueScope->role();
    }

    public function league(): League
    {
        return $this->leagueScope->league();
    }

    public function season(): Season
    {
        return $this->season;
    }
}
