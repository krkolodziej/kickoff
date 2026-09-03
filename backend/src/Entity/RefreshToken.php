<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken as BaseRefreshToken;

/**
 * The long-lived half of the session.
 *
 * The bundle ships its half as a mapped superclass with XML mapping (id, refresh_token,
 * username, valid); the application only has to declare the concrete entity, so that the
 * table lives in our schema and is created by our migrations. Redeclaring those columns
 * here with attributes would be a duplicate mapping and Doctrine refuses it outright —
 * which is a neat demonstration that a mapped superclass really is mapping, inherited.
 *
 * `hash_tokens` is enabled in the bundle configuration, so the `refresh_token` column holds
 * a hash of the value the browser was handed, not the value itself: a leaked database dump
 * cannot be replayed against the refresh endpoint.
 */
#[ORM\Entity]
#[ORM\Table(name: 'refresh_tokens')]
class RefreshToken extends BaseRefreshToken
{
}
