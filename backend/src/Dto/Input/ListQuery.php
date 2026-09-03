<?php

declare(strict_types=1);

namespace App\Dto\Input;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * The four things every collection endpoint accepts, in one place.
 *
 * Consumed with #[MapQueryString], which runs the same serializer and validator as a request
 * body — so `page_size=0` is a 422 with a message, not a division by zero further down.
 *
 * Paging is opt-in. Sending neither `page` nor `page_size` returns a plain JSON array;
 * sending either switches the response to the paginated envelope. That keeps the small
 * collections that dominate this application (twelve clubs, eighteen players) free of
 * ceremony, while still giving a client a way to walk a long one.
 */
final class ListQuery
{
    public const DEFAULT_PAGE_SIZE = 20;

    public function __construct(
        #[Assert\Length(max: 100)]
        public string $search = '',
        #[Assert\Positive(message: 'Page numbers start at 1.')]
        public ?int $page = null,
        #[Assert\Range(min: 1, max: 100, notInRangeMessage: 'Ask for between {{ min }} and {{ max }} rows.')]
        public ?int $pageSize = null,
        #[Assert\Length(max: 64)]
        public ?string $order = null,
    ) {
    }

    public function isPaginated(): bool
    {
        return null !== $this->page || null !== $this->pageSize;
    }

    public function pageNumber(): int
    {
        return $this->page ?? 1;
    }

    public function size(): int
    {
        return $this->pageSize ?? self::DEFAULT_PAGE_SIZE;
    }

    public function offset(): int
    {
        return ($this->pageNumber() - 1) * $this->size();
    }

    public function searchTerm(): ?string
    {
        $term = trim($this->search);

        return '' === $term ? null : $term;
    }
}
