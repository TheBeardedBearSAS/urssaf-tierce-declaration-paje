<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Scheduler;

use Override;
use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * The safety crons of the package (ADR 0013, decision 10) as a Symfony Scheduler schedule: each task runs a console
 * command through Messenger ({@see RunCommandMessage}), exactly like the crontab of the documentation (same locks, same
 * safety nets). The command requeuing the declarations refused for invalid credentials is not scheduled (it is run by hand
 * after fixing the credentials), and no scheduled command submits a declaration (ADR 0013, decision 2).
 *
 * Needs symfony/scheduler 6.4.3 or later (the `conflict` of composer.json, ADR 0000).
 */
final readonly class UrssafScheduleProvider implements ScheduleProviderInterface
{
    /** Console commands of each task of the "scheduler.frequencies" option. */
    public const TASKS = [
        'sync_employer_statuses' => ['urssaf:paje:employers:sync-status'],
        'reconcile_stale_declarations' => ['urssaf:paje:declarations:reconcile-stale'],
        'relay_outbox' => ['urssaf:paje:outbox:relay'],
        'purge_outbox' => ['urssaf:paje:outbox:purge'],
    ];

    /**
     * @param array<string, ?string> $frequencies ISO 8601 duration of each task, null to leave it out
     */
    public function __construct(private array $frequencies) {}

    #[Override]
    public function getSchedule(): Schedule
    {
        $schedule = new Schedule();
        foreach (self::TASKS as $task => $commands) {
            $frequency = $this->frequencies[$task] ?? null;
            if ($frequency === null) {
                continue;
            }
            foreach ($commands as $command) {
                $schedule->add(RecurringMessage::every($frequency, new RunCommandMessage($command)));
            }
        }

        return $schedule;
    }
}
