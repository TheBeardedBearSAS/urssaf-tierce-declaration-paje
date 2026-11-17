<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Command;

use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\ReconcileStaleDeclarations;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\ReconcileStaleDeclarationsHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Clock;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Lock\OperationLocks;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecord;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Instant;

/**
 * Moves the declarations left "submitting" for longer than "workflow.stale_submission_after" to "outcome_unknown": they
 * are never resent (ADR 0003). Exits with 1 when a record was moved (an alert for the operator, who then checks on
 * pajemploi.urssaf.fr and runs urssaf:paje:declaration:resolve). Safety cron: every 15 minutes.
 */
#[AsCommand(name: 'urssaf:paje:declarations:reconcile-stale', description: 'Move the declarations left submitting to outcome_unknown (never resent, ADR 0003)', aliases: ['urssaf:paje:declarations:reconcile'])]
final class ReconcileStaleDeclarationsCommand extends Command
{
    public function __construct(
        private readonly DeclarationRecordRepository $declarations,
        private readonly ReconcileStaleDeclarationsHandler $reconcile,
        private readonly Clock $clock,
        private readonly WorkflowPolicy $policy,
        private readonly OperationLocks $locks,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        CommandOptions::addLimit($this);
        CommandOptions::addDryRun($this);
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return CommandReports::guard($output, function () use ($input, $output): int {
            $limit = CommandOptions::limit($input);
            if (CommandOptions::dryRun($input)) {
                $olderThan = Instant::fromDateTime($this->clock->now()->sub($this->policy->staleSubmissionAfter()));

                return CommandReports::renderPlan($output, array_map(
                    static fn(DeclarationRecord $record): array => [$record->tenantId()->value(), $record->id()->value()],
                    $this->declarations->findStaleSubmissions($olderThan, $this->policy->pageSize($limit)),
                ));
            }

            return $this->locks->exclusively(
                'declarations.reconcile-stale',
                '*',
                fn(): int => CommandReports::renderReport($output, ($this->reconcile)(new ReconcileStaleDeclarations($limit))),
                static fn(string $resource): int => CommandReports::renderBusy($output, $resource),
            );
        });
    }
}
