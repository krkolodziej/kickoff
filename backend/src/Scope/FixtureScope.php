<?php

declare(strict_types=1);

namespace App\Scope;

use App\Entity\Fixture;
use App\Entity\Organization;
use App\Entity\OrganizationRole;
use App\Entity\Season;

final readonly class FixtureScope implements ScopeInterface
{
    public function __construct(
        private SeasonScope $seasonScope,
        private Fixture $fixture,
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

    public function fixture(): Fixture
    {
        return $this->fixture;
    }
}
