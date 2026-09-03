<?php

declare(strict_types=1);

namespace App\Scope;

use App\Entity\Organization;
use App\Entity\OrganizationRole;
use App\Entity\Season;
use App\Entity\SeasonTeam;

/**
 * The deepest scope so far: organization → league → season → registered club, all proven by
 * the one query that built it. Four levels of nesting in the URL, and still no controller
 * that has to check anything.
 */
final readonly class SeasonTeamScope implements ScopeInterface
{
    public function __construct(
        private SeasonScope $seasonScope,
        private SeasonTeam $seasonTeam,
    ) {
    }

    public function organization(): Organization
    {
        return $this->seasonScope->organization();
    }

    public function role(): OrganizationRole
    {
        return $this->seasonScope->role();
    }

    public function season(): Season
    {
        return $this->seasonScope->season();
    }

    public function seasonTeam(): SeasonTeam
    {
        return $this->seasonTeam;
    }
}
