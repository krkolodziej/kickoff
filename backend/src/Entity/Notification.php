<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\NotificationRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Something that happened, addressed to one person.
 *
 * The column that matters most is `dedupe_key`, and it is unique on purpose.
 *
 * A message queue promises *at least once*, not exactly once: a worker that dies between
 * doing the work and acknowledging the message will be handed the same message again, and a
 * retry after a transient database error does the same. Without a key like this, a handler
 * that runs twice leaves two identical lines in somebody's bell — and the fix cannot be "make
 * the worker reliable", because that is not a promise any queue makes.
 *
 * So the rule is enforced where it cannot be raced: the database refuses the second insert,
 * the handler treats that refusal as success, and delivering a message twice becomes boring.
 */
#[ORM\Entity(repositoryClass: NotificationRepository::class)]
#[ORM\Table(name: 'notifications')]
#[ORM\UniqueConstraint(name: 'uniq_notification_dedupe', columns: ['dedupe_key'])]
// Reading the bell is "this user's unread, newest first", and that is the only query that
// runs on every page. Both columns are in the index so it never touches the table to filter.
#[ORM\Index(name: 'idx_notification_recipient_read', columns: ['recipient_id', 'read_at', 'created_at'])]
#[ORM\HasLifecycleCallbacks]
class Notification
{
    use TimestampsTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $recipient;

    /**
     * Which organization the notification belongs to.
     *
     * Not decoration: a person can be in several, and a bell that mixes them without saying
     * which is which is a bell nobody trusts. It also means leaving an organization can take
     * its notifications with it.
     */
    #[ORM\ManyToOne(targetEntity: Organization::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Organization $organization;

    #[ORM\Column(length: 32, enumType: NotificationType::class)]
    private NotificationType $type;

    #[ORM\Column(length: 160)]
    private string $title;

    #[ORM\Column(length: 320)]
    private string $body;

    /**
     * Where the bell takes you. A path within the application, never an absolute URL: the
     * host is the client's business and baking one in makes every stored row wrong the day
     * the domain changes.
     */
    #[ORM\Column(length: 255)]
    private string $link;

    #[ORM\Column(length: 191)]
    private string $dedupeKey;

    #[ORM\Column(type: 'datetime_immutable', nullable: true, options: ['comment' => 'UTC'])]
    private ?\DateTimeImmutable $readAt = null;

    public function __construct(
        User $recipient,
        Organization $organization,
        NotificationType $type,
        string $title,
        string $body,
        string $link,
        string $dedupeKey,
    ) {
        $this->recipient = $recipient;
        $this->organization = $organization;
        $this->type = $type;
        $this->title = $title;
        $this->body = $body;
        $this->link = $link;
        $this->dedupeKey = $dedupeKey;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRecipient(): User
    {
        return $this->recipient;
    }

    public function getOrganization(): Organization
    {
        return $this->organization;
    }

    public function getType(): NotificationType
    {
        return $this->type;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getLink(): string
    {
        return $this->link;
    }

    public function getDedupeKey(): string
    {
        return $this->dedupeKey;
    }

    public function getReadAt(): ?\DateTimeImmutable
    {
        return $this->readAt;
    }

    public function isRead(): bool
    {
        return null !== $this->readAt;
    }

    /**
     * Marking an already-read notification read again leaves the original time alone. The
     * question the timestamp answers is "when did they first see this", and answering it
     * differently on every page load would make it useless.
     */
    public function markRead(\DateTimeImmutable $at): static
    {
        $this->readAt ??= $at;

        return $this;
    }
}
