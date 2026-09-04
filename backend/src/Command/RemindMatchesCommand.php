<?php

declare(strict_types=1);

namespace App\Command;

use App\Domain\Notification\KickOffReminder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * The same scan the schedule runs, on demand.
 *
 * Worth having for a reason that outlives the demo: when reminders stop arriving, the first
 * question is whether the scan is broken or the worker is not running. A command answers that
 * in one line. Without it the only way to test the job is to start a worker and wait up to
 * fifteen minutes, which is long enough that people stop checking.
 *
 * It is deliberately the same object, not a copy of the logic. Two implementations of "which
 * matches are a day away" would drift, and the copy that drifts is the one nobody runs.
 */
#[AsCommand(
    name: 'app:matches:remind',
    description: 'Notify organizers about matches kicking off in about a day.',
)]
final class RemindMatchesCommand extends Command
{
    public function __construct(private readonly KickOffReminder $reminder)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $result = $this->reminder->run();

        if (0 === $result['matches']) {
            $io->success('Nothing kicks off in that window.');

            return Command::SUCCESS;
        }

        $io->success(\sprintf(
            '%d match%s in the window, %d notification%s sent.',
            $result['matches'],
            1 === $result['matches'] ? '' : 'es',
            $result['notifications'],
            1 === $result['notifications'] ? '' : 's',
        ));

        // Nought notifications from several matches is normal rather than suspicious: it
        // means everybody has already been told, which is what the dedupe key is for.
        if (0 === $result['notifications']) {
            $io->note('Everybody had already been told about those.');
        }

        return Command::SUCCESS;
    }
}
