<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Turns a name into a URL-safe slug, and keeps trying until it finds a free one.
 *
 * The locale matters more than it looks. `new AsciiSlugger()` without one transliterates
 * with a generic table, and Polish club names come out mangled — "Łódź" loses characters
 * instead of becoming "lodz". Passing 'pl' selects the Polish transliteration rules, which
 * is exactly the kind of thing that is invisible until seed data arrives.
 *
 * Uniqueness is settled by suffixing rather than by rejecting the request. Two organizations
 * may legitimately be called the same thing, and a user who never typed a slug should not be
 * shown an error about one.
 */
final class SlugGenerator
{
    private readonly AsciiSlugger $slugger;

    public function __construct()
    {
        $this->slugger = new AsciiSlugger('pl');
    }

    public function slugify(string $value): string
    {
        return $this->slugger->slug($value)->lower()->truncate(64, '')->toString();
    }

    /**
     * @param callable(string): bool $isTaken
     */
    public function uniqueSlug(string $value, callable $isTaken, int $maxLength = 64): string
    {
        $base = $this->slugify($value);

        if ('' === $base) {
            $base = 'item';
        }

        $base = substr($base, 0, $maxLength);

        if (!$isTaken($base)) {
            return $base;
        }

        for ($suffix = 2; $suffix < 1000; ++$suffix) {
            $tail = '-'.$suffix;
            $candidate = substr($base, 0, $maxLength - \strlen($tail)).$tail;

            if (!$isTaken($candidate)) {
                return $candidate;
            }
        }

        // A thousand organizations with the same name is not a case worth designing for,
        // but silently returning a duplicate would hit the unique index as a 500.
        throw new \RuntimeException(\sprintf('Could not find a free slug based on "%s".', $value));
    }
}
