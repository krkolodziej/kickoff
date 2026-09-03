<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpFoundation\Response;

/**
 * Ownership cannot be edited away or deleted through the members API.
 *
 * 403 rather than 409, because this is not about the current state of the data: no sequence
 * of requests makes it allowed. Transferring ownership is a deliberate operation that
 * should look like one, not something that falls out of a role dropdown.
 */
final class OwnerMembershipIsProtectedException extends DomainException
{
    public function __construct()
    {
        parent::__construct('The owner of an organization cannot be changed or removed here.');
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_FORBIDDEN;
    }

    public function getErrorCode(): string
    {
        return 'owner_membership_protected';
    }
}
