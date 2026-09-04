<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notification>
 */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    /**
     * Which of these keys are already in the table.
     *
     * One query for the whole batch rather than one per recipient: a finished match notifies
     * every owner and admin of the organization, and asking the database the same question
     * once per person is the sort of loop that only shows up in production.
     *
     * @param list<string> $keys
     *
     * @return list<string>
     */
    public function existingDedupeKeys(array $keys): array
    {
        if ([] === $keys) {
            return [];
        }

        /** @var list<array{dedupeKey: string}> $rows */
        $rows = $this->createQueryBuilder('n')
            ->select('n.dedupeKey AS dedupeKey')
            ->where('n.dedupeKey IN (:keys)')
            ->setParameter('keys', $keys)
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $row): string => $row['dedupeKey'], $rows);
    }

    public function unreadCount(User $user): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.recipient = :user')
            ->andWhere('n.readAt IS NULL')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * The bell's contents, newest first.
     *
     * Capped rather than paginated. A notification list is read by scrolling the top of it,
     * and nobody has ever wanted page four of their own bell; a limit keeps the query flat
     * and the response small without inventing an interface for it.
     *
     * @return list<Notification>
     */
    public function findForUser(User $user, bool $unreadOnly = false, int $limit = 30): array
    {
        $qb = $this->createQueryBuilder('n')
            ->addSelect('o')
            ->innerJoin('n.organization', 'o')
            ->where('n.recipient = :user')
            ->setParameter('user', $user)
            ->orderBy('n.createdAt', 'DESC')
            // Two notifications created in the same second are common — a finished match
            // notifies several people at once — so the id settles the order rather than
            // leaving it to the database.
            ->addOrderBy('n.id', 'DESC')
            ->setMaxResults($limit);

        if ($unreadOnly) {
            $qb->andWhere('n.readAt IS NULL');
        }

        /* @var list<Notification> */
        return $qb->getQuery()->getResult();
    }

    /**
     * Marks everything unread as read, in one statement.
     *
     * `n.readAt IS NULL` in the WHERE is not an optimisation: without it, "mark all read"
     * would rewrite the timestamp on notifications the reader saw last week, and the column
     * would stop answering the question it exists for.
     */
    public function markAllRead(User $user, \DateTimeImmutable $at): int
    {
        return (int) $this->createQueryBuilder('n')
            ->update()
            ->set('n.readAt', ':at')
            ->where('n.recipient = :user')
            ->andWhere('n.readAt IS NULL')
            ->setParameter('at', $at)
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }
}
