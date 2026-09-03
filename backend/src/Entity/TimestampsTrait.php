<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Every table in this schema carries the same two columns, so they are declared once here.
 *
 * Doctrine fills them through lifecycle callbacks rather than through a database DEFAULT,
 * which keeps the behaviour identical on MariaDB, MySQL 8 and anything else, and keeps the
 * values readable in PHP the moment an entity is persisted instead of only after a refetch.
 * The owning entity must be annotated `#[ORM\HasLifecycleCallbacks]` for these to run.
 */
trait TimestampsTrait
{
    #[ORM\Column(type: 'datetime_immutable', options: ['comment' => 'UTC'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', options: ['comment' => 'UTC'])]
    private \DateTimeImmutable $updatedAt;

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    #[ORM\PrePersist]
    public function initialiseTimestamps(): void
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function refreshUpdatedAt(): void
    {
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}
