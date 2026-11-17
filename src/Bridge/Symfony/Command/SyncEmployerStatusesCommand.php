<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Command;

use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\SyncEmployerStatus;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\SyncEmployerStatuses;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\SyncEmployerStatusesHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\SyncEmployerStatusHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\BatchReport;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\EmployerSelector;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Lock\OperationLocks;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Message\SyncEmployerStatusesMessage;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;

/**
 * Refreshes the tracked employers from URSSAF (method 020, a read): one employer given by a selector (of --tenant, else of the only configured tenant), the least recently
 * checked employers of a tenant, or of every tenant. `--dry-run` lists what would be refreshed; `--async` dispatches one
 * message per tenant instead (Messenger, ADR 0013). The only polling of the package: run it daily.
 */
#[AsCommand(name: 'urssaf:paje:employers:sync-status', description: 'Refresh the status of the tracked employers (method 020)')]
final class SyncEmployerStatusesCommand extends Command
{
    private const OPTION_ASYNC = 'async';
    private const LOCK_ONE = 'employer.sync-status';
    private const LOCK_TENANT = 'employers.sync-status';

    /**
     * @param list<string> $configuredTenants
     */
    public function __construct(
        private readonly EmployerAccountRepository $accounts,
        private readonly SyncEmployerStatusHandler $syncOne,
        private readonly SyncEmployerStatusesHandler $syncTenant,
        private readonly WorkflowPolicy $policy,
        private readonly OperationLocks $locks,
        private readonly CommandLocks $employerLocks,
        private readonly ?MessageBusInterface $bus,
        private readonly array $configuredTenants,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        CommandOptions::addTenant($this, 'Tenant id (default: every tenant; with an employer selector, the only configured tenant)');
        CommandSelectors::addEmployerSelector($this);
        CommandOptions::addLimit($this);
        CommandOptions::addDryRun($this);
        $this->addOption(self::OPTION_ASYNC, null, InputOption::VALUE_NONE, 'Dispatch one Messenger message per tenant instead of refreshing now');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return CommandReports::guard($output, function () use ($input, $output): int {
            $selector = CommandSelectors::optionalEmployerSelector($input);
            $limit = CommandOptions::limit($input);
            $dryRun = CommandOptions::dryRun($input);
            $async = $input->getOption(self::OPTION_ASYNC) === true;
            if ($selector instanceof EmployerSelector) {
                if ($limit !== null || $dryRun || $async) {
                    throw new InvalidOptionException('An employer selector cannot be combined with --limit, --dry-run or --async.');
                }

                return $this->refreshOne($input, $output, $selector);
            }
            if ($async) {
                return $dryRun ? throw new InvalidOptionException('--async cannot be combined with --dry-run.') : $this->dispatch($input, $output);
            }

            return $dryRun ? $this->plan($input, $output, $limit) : $this->refreshTenants($input, $output, $limit);
        });
    }

    private function refreshOne(InputInterface $input, OutputInterface $output, EmployerSelector $selector): int
    {
        $tenant = CommandTenants::tenantOrSingleConfigured($input, $this->configuredTenants);

        return $this->employerLocks->forEmployer(
            $output,
            $tenant,
            $selector,
            self::LOCK_ONE,
            function () use ($output, $tenant, $selector): int {
                CommandReports::renderFields($output, CommandViews::refreshedAccount(($this->syncOne)(new SyncEmployerStatus($tenant, $selector))));

                return Command::SUCCESS;
            },
        );
    }

    private function refreshTenants(InputInterface $input, OutputInterface $output, ?int $limit): int
    {
        $report = BatchReport::of();
        foreach ($this->tenants($input) as $tenant) {
            $report = $report->merge($this->locks->exclusively(
                self::LOCK_TENANT,
                $tenant->value(),
                fn(): BatchReport => ($this->syncTenant)(new SyncEmployerStatuses($tenant, $limit)),
                static function (string $resource) use ($output): BatchReport {
                    CommandReports::renderBusy($output, $resource);

                    return BatchReport::of();
                },
            ));
        }

        return CommandReports::renderReport($output, $report);
    }

    private function dispatch(InputInterface $input, OutputInterface $output): int
    {
        $bus = $this->bus ?? throw new InvalidOptionException('--async requires "messenger.enabled".');
        $tenants = $this->tenants($input);
        foreach ($tenants as $tenant) {
            $bus->dispatch(new SyncEmployerStatusesMessage($tenant->value()));
        }
        $output->writeln(\sprintf('%d message(s) dispatched.', \count($tenants)));

        return Command::SUCCESS;
    }

    private function plan(InputInterface $input, OutputInterface $output, ?int $limit): int
    {
        $rows = [];
        foreach ($this->tenants($input) as $tenant) {
            foreach ($this->accounts->findLeastRecentlyChecked($tenant, $this->policy->pageSize($limit)) as $account) {
                $rows[] = [$tenant->value(), $account->id()->value()];
            }
        }

        return CommandReports::renderPlan($output, $rows);
    }

    /**
     * @return list<TenantId> the --tenant, else every tenant with tracked employers
     */
    private function tenants(InputInterface $input): array
    {
        $tenant = CommandOptions::tenant($input);

        return $tenant instanceof TenantId ? [$tenant] : $this->accounts->tenants();
    }
}
