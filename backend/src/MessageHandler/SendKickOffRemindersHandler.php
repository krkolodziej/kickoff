<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Domain\Notification\KickOffReminder;
use App\Message\SendKickOffReminders;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Three lines, on purpose. The scan itself is a service so that `app:matches:remind` can run
 * exactly the same code without a message and a worker in between.
 */
#[AsMessageHandler]
final readonly class SendKickOffRemindersHandler
{
    public function __construct(private KickOffReminder $reminder)
    {
    }

    public function __invoke(SendKickOffReminders $message): void
    {
        $this->reminder->run();
    }
}
