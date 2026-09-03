<?php

declare(strict_types=1);

namespace App\Scope;

use App\Entity\User;
use App\Repository\OrganizationMembershipRepository;
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
}
