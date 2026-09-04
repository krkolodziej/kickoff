<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\Output\NotificationResource;
use App\Entity\User;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * The bell.
 *
 * The only resource in this application that is **not** scoped to an organization, and the
 * only one whose controller therefore takes no scope. A notification belongs to a person, and
 * a person can be in several organizations, so the boundary here is the authenticated user
 * rather than a membership row. That also means there is no voter to consult: there is no
 * question of what this user may do to somebody else's notification, because the query never
 * returns one.
 */
#[Route('/api/v1/notifications')]
final class NotificationController extends AbstractController
{
    public function __construct(
        private readonly NotificationRepository $notifications,
        private readonly EntityManagerInterface $entityManager,
        private readonly ClockInterface $clock,
    ) {
    }

    #[Route('', name: 'api_notifications_list', methods: ['GET'])]
    public function list(#[CurrentUser] User $user, Request $request): JsonResponse
    {
        $rows = $this->notifications->findForUser(
            $user,
            unreadOnly: $request->query->getBoolean('unread'),
        );

        return $this->json(array_map(NotificationResource::fromEntity(...), $rows));
    }

    /**
     * Its own endpoint, and a deliberately tiny one.
     *
     * The bell asks for this on a timer while the list is only fetched when somebody opens the
     * panel. Returning the count as part of the list would make every poll drag thirty rows
     * and two joins across the wire to render one number.
     */
    #[Route('/unread-count', name: 'api_notifications_unread_count', methods: ['GET'])]
    public function unreadCount(#[CurrentUser] User $user): JsonResponse
    {
        return $this->json(['count' => $this->notifications->unreadCount($user)]);
    }

    /**
     * POST rather than PATCH on each notification: "I have seen these" is one act by the
     * reader, not an edit to a list of records, and making the client issue thirty requests
     * to express it would be an interface built out of the storage rather than the intent.
     */
    #[Route('/read', name: 'api_notifications_mark_read', methods: ['POST'])]
    public function markAllRead(#[CurrentUser] User $user): JsonResponse
    {
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        return $this->json(['marked' => $this->notifications->markAllRead($user, $now)]);
    }

    /**
     * Marking one read is scoped by the query, not checked afterwards. Asking for somebody
     * else's notification finds nothing and answers 404 — the same rule the rest of the
     * application follows, for the same reason: a 403 would confirm the row exists.
     */
    #[Route('/{id<\d+>}/read', name: 'api_notification_mark_read', methods: ['POST'])]
    public function markRead(#[CurrentUser] User $user, int $id): JsonResponse
    {
        $notification = $this->notifications->findOneBy(['id' => $id, 'recipient' => $user]);

        if (null === $notification) {
            throw $this->createNotFoundException();
        }

        $notification->markRead(\DateTimeImmutable::createFromInterface($this->clock->now()));
        $this->entityManager->flush();

        return $this->json(NotificationResource::fromEntity($notification), Response::HTTP_OK);
    }
}
