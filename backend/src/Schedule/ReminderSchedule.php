<?php

declare(strict_types=1);

namespace App\Schedule;

use App\Message\SendKickOffReminders;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * The recurring work, declared in PHP rather than in crontab.
 *
 * What this buys over a system cron entry: it is in the repository, it is reviewed with the
 * code it triggers, it deploys with it, and it is the same on every machine. A crontab line
 * lives on one server, is edited by whoever has a shell there, and is discovered missing
 * after the reminders stop arriving.
 *
 * What it costs: a process has to be running. `messenger:consume scheduler_reminders` is that
 * process, and if it is not running nothing fires — which is the same failure mode as a
 * stopped cron daemon, only easier to forget because it does not look like infrastructure.
 */
#[AsSchedule('reminders')]
final class ReminderSchedule implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        return (new Schedule())
            ->add(
                // Every quarter of an hour, matched to the ±15 minute window the scan uses,
                // so that each match falls into exactly one run. See KickOffReminder.
                RecurringMessage::every('15 minutes', new SendKickOffReminders()),
            );
    }
}
