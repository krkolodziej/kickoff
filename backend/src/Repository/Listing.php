<?php

declare(strict_types=1);

namespace App\Repository;

use App\Dto\Input\ListQuery;
use App\Dto\Output\ResultPage;
use App\Exception\InvalidOrderingException;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator as DoctrinePaginator;

/**
 * Turns a QueryBuilder plus a ListQuery into what a collection endpoint returns.
 *
 * Written once, used by every list in the application, so that "how does search work" and
 * "what does page 2 look like" have one answer rather than one per controller.
 */
final class Listing
{
    /**
     * Applies an allow-listed sort.
     *
     * The allow-list maps a *wire* name onto a DQL expression, which is the point: a caller
     * never names a column, so `order` can never be used to sort by something the resource
     * does not expose, and renaming a property does not change the API.
     *
     * @param array<string, string> $allowed wire name => DQL expression
     */
    public function sort(QueryBuilder $qb, ListQuery $query, array $allowed, string $default): void
    {
        $requested = $query->order ?? $default;
        $descending = str_starts_with($requested, '-');
        $field = ltrim($requested, '-');

        if (!isset($allowed[$field])) {
            throw new InvalidOrderingException($field, array_keys($allowed));
        }

        $qb->orderBy($allowed[$field], $descending ? 'DESC' : 'ASC');

        // A tiebreaker on the primary key, always. Without one, two rows with the same name
        // can swap places between requests, and a reader paging through the list sees one of
        // them twice and never sees the other.
        $qb->addOrderBy($qb->getRootAliases()[0].'.id', 'ASC');
    }

    /**
     * @template TEntity of object
     * @template TResource
     *
     * @param callable(TEntity): TResource $map
     *
     * @return list<TResource>|ResultPage<TResource>
     */
    public function respond(QueryBuilder $qb, ListQuery $query, callable $map): array|ResultPage
    {
        if (!$query->isPaginated()) {
            /** @var list<TEntity> $rows */
            $rows = $qb->getQuery()->getResult();

            return array_map($map, $rows);
        }

        $qb->setFirstResult($query->offset())->setMaxResults($query->size());

        // fetchJoinCollection: false because these lists join to-one relations only. Set it
        // when a query fetch-joins a collection, or LIMIT applies to the joined rows rather
        // than to the entities and a page comes back short.
        $paginator = new DoctrinePaginator($qb->getQuery(), fetchJoinCollection: false);

        $count = \count($paginator);
        $page = $query->pageNumber();
        $lastPage = max(1, (int) ceil($count / $query->size()));

        /** @var list<TEntity> $rows */
        $rows = iterator_to_array($paginator, false);

        return new ResultPage(
            count: $count,
            page: $page,
            pageSize: $query->size(),
            next: $page < $lastPage ? $page + 1 : null,
            previous: $page > 1 ? $page - 1 : null,
            results: array_map($map, $rows),
        );
    }
}
