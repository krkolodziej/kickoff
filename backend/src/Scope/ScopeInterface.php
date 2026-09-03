<?php

declare(strict_types=1);

namespace App\Scope;

use App\Entity\Organization;
use App\Entity\OrganizationRole;

/**
 * A resolved position inside one organization: which organization, and with what authority.
 *
 * A scope can only be constructed by ScopeFactory, and ScopeFactory can only build one from
 * a query that joins the caller's membership. So a controller holding a scope is holding
 * proof that the caller belongs there — the check cannot be forgotten, because there is no
 * way to obtain the object without it.
 *
 * Later stages add LeagueScope, SeasonScope and MatchScope, each carrying the whole chain
 * above it and each implementing this interface.
 */
interface ScopeInterface
{
    public function organization(): Organization;

    public function role(): OrganizationRole;
}
