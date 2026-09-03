<?php

declare(strict_types=1);

namespace App\Scope;

use App\Entity\League;
use App\Entity\Organization;
use App\Entity\OrganizationRole;

/**
 * A league, plus the organization it belongs to and the caller's authority there.
 *
 * The whole chain travels together. A controller that needs the organization has it without
 * a second query, and — more importantly — a league can only be reached through the
 * organization that owns it, so `/organizations/3/leagues/9` is a 404 when league 9 belongs
 * to organization 4, rather than quietly working.
 */
final readonly class LeagueScope implements ScopeInterface
{
    public function __construct(
        private OrganizationScope $organizationScope,
        private League $league,
    ) {
    }

    public function organization(): Organization
    {
        return $this->organizationScope->organization();
    }

    public function role(): OrganizationRole
    {
        return $this->organizationScope->role();
    }

    public function league(): League
    {
        return $this->league;
    }
}
