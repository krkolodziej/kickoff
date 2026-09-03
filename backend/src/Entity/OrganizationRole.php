<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Authority inside one organization.
 *
 * Deliberately not a Symfony role. `ROLE_ADMIN` in the security token would mean "an
 * administrator of everything", which is exactly the mistake this application is built to
 * avoid: someone can own one league and be a spectator in another. The value therefore
 * lives on the membership row and is read by a voter, not by `getRoles()`.
 */
enum OrganizationRole: string
{
    case Owner = 'OWNER';
    case Admin = 'ADMIN';
    case Member = 'MEMBER';

    /** May create, edit and delete the competition data inside this organization. */
    public function canManage(): bool
    {
        return self::Owner === $this || self::Admin === $this;
    }

    /**
     * The roles the API is willing to hand out.
     *
     * Ownership is not among them: it is established once, when the organization is
     * created, and there is no endpoint that mints a second owner. Transferring it is a
     * deliberate feature, not something that should fall out of a role dropdown.
     *
     * @return list<self>
     */
    public static function assignable(): array
    {
        return [self::Admin, self::Member];
    }
}
