<?php

declare(strict_types=1);

namespace App\Domain\Notification;

use App\Entity\Notification;
use App\Entity\NotificationType;
use App\Entity\Organization;
use App\Entity\User;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Puts a notification in front of people, at most once each.
 *
 * "At most once" is the whole job. A queue promises *at least once* delivery, so a handler
 * has to be safe to run again — and the way that is achieved here is worth reading carefully,
 * because it is three mechanisms working together and any one of them alone is not enough:
 *
 * 1. **A key that describes the fact, not the delivery.** `MATCH_FINISHED:41:7` says "user 7
 *    was told about match 41". Running the handler again computes the same key.
 * 2. **A unique index on that key.** This is the only part that cannot be raced. Two workers
 *    handling the same message at the same instant both pass the check below; the database
 *    lets exactly one of them win.
 * 3. **A check before inserting.** Not the guarantee — the index is — but it turns the common
 *    case (a redelivery seconds or hours later) into a query and no write, instead of a
 *    failed transaction and a retry.
 *
 * When the race does happen, the flush throws, the message is retried, and on the retry step
 * 3 finds every key already there and does nothing. The failure resolves itself.
 */
final readonly class Notifier
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private NotificationRepository $notifications,
    ) {
    }

    /**
     * @param list<User> $recipients
     *
     * @return int how many were actually created
     */
    public function deliver(
        array $recipients,
        Organization $organization,
        NotificationType $type,
        string $subject,
        string $title,
        string $body,
        string $link,
    ): int {
        if ([] === $recipients) {
            return 0;
        }

        $keys = [];

        foreach ($recipients as $recipient) {
            $keys[(int) $recipient->getId()] = self::dedupeKey($type, $subject, $recipient);
        }

        $already = array_flip($this->notifications->existingDedupeKeys(array_values($keys)));
        $created = 0;

        foreach ($recipients as $recipient) {
            $key = $keys[(int) $recipient->getId()];

            if (isset($already[$key])) {
                continue;
            }

            $this->entityManager->persist(
                new Notification($recipient, $organization, $type, $title, $body, $link, $key),
            );
            ++$created;
        }

        if ($created > 0) {
            $this->entityManager->flush();
        }

        return $created;
    }

    /**
     * `<type>:<subject>:<recipient>`.
     *
     * The subject is whatever makes the fact unique for that type — a fixture id for both of
     * the types that exist today. Deliberately not a timestamp or a message id: those change
     * between deliveries of the same fact, which is exactly the thing this key must not do.
     */
    public static function dedupeKey(NotificationType $type, string $subject, User $recipient): string
    {
        return \sprintf('%s:%s:%d', $type->value, $subject, (int) $recipient->getId());
    }
}
