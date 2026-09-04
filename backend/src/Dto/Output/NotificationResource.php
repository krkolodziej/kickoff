<?php

declare(strict_types=1);

namespace App\Dto\Output;

use App\Entity\Notification;
use App\Entity\NotificationType;
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;

final readonly class NotificationResource
{
    public function __construct(
        public int $id,
        public NotificationType $type,
        public string $title,
        public string $body,
        /** A path inside the application, so the client can route to it without parsing. */
        public string $link,
        public int $organizationId,
        public string $organizationName,
        #[Context([DateTimeNormalizer::FORMAT_KEY => \DateTimeInterface::ATOM])]
        public \DateTimeImmutable $createdAt,
        #[Context([DateTimeNormalizer::FORMAT_KEY => \DateTimeInterface::ATOM])]
        public ?\DateTimeImmutable $readAt,
    ) {
    }

    public static function fromEntity(Notification $notification): self
    {
        $organization = $notification->getOrganization();

        return new self(
            id: (int) $notification->getId(),
            type: $notification->getType(),
            title: $notification->getTitle(),
            body: $notification->getBody(),
            link: $notification->getLink(),
            organizationId: (int) $organization->getId(),
            organizationName: $organization->getName(),
            createdAt: $notification->getCreatedAt(),
            readAt: $notification->getReadAt(),
        );
    }
}
