<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * A season is named either for one year ("2026") or for two consecutive ones ("2026/27").
 *
 * This is the case where a hand-written constraint earns its place. A `Regex` can insist on
 * the *shape* `\d{4}(/\d{2})?` but not on the *arithmetic*: "2026/27" is a season and
 * "2026/29" is a typo, and no pattern can tell them apart. The rule needs to parse both
 * halves and compare them, which is what a validator is for.
 *
 * Note what it does not need: a repository, a request, or anything but the value itself.
 * Rules that need the database live in the domain service instead — see SquadManager — for
 * the simple reason that a validator has no way to know which organization is asking.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class SeasonName extends Constraint
{
    public string $shapeMessage = 'Name a season for one year ("2026") or two ("2026/27").';
    public string $sequenceMessage = 'The second year has to follow the first: "{{ expected }}", not "{{ given }}".';
}
