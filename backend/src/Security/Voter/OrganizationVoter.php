<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\OrganizationRole;
use App\Scope\ScopeInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * What a member may do with an organization they have already been proven to belong to.
 *
 * The division of labour is worth saying out loud, because it is the thing people get
 * wrong: **the scope decides whether a resource exists for you; the voter decides what you
 * may do with a resource that does exist.**
 *
 * Repository scoping alone cannot express "a member may read but not write". A voter alone
 * cannot express "this resource is invisible to you" without leaking 403s that confirm it
 * is there. Both are needed, and they answer different questions.
 *
 * Note what this voter does *not* do: it runs no queries. The role travelled here inside the
 * scope, loaded by the same query that proved membership, so authorization costs nothing.
 *
 * @extends Voter<string, ScopeInterface>
 */
final class OrganizationVoter extends Voter
{
    /** Read anything inside the organization. */
    public const VIEW = 'ORG_VIEW';

    /** Create, edit and delete the competition data inside it. */
    public const MANAGE = 'ORG_MANAGE';

    /** Act on the organization itself — today, delete it. */
    public const OWN = 'ORG_OWN';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::VIEW, self::MANAGE, self::OWN], true)
            && $subject instanceof ScopeInterface;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        // The generic annotation on the class tells static analysis that supports() has
        // already narrowed the subject, so no runtime re-check is needed here.
        $role = $subject->role();

        return match ($attribute) {
            // Holding the scope is the proof. Reaching this line at all means the membership
            // query returned a row, so there is nothing left to check.
            self::VIEW => true,
            self::MANAGE => $role->canManage(),
            self::OWN => OrganizationRole::Owner === $role,
            default => false,
        };
    }
}
