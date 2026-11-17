<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Command;

use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\RecordMandateCancellation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\RecordMandateCancellationHandler;

/**
 * Records the cancellation of the mandate of a tracked employer without any URSSAF call: after an accepted 031 whose recording failed, or a mandate cancelled on pajemploi.urssaf.fr. Idempotent, under the lock of the employer (ADR 0006).
 */
#[AsCommand(name: 'urssaf:paje:mandate:record-cancellation', description: 'Record the cancellation of a mandate cancelled outside the package (no URSSAF call)')]
final class RecordMandateCancellationCommand extends Command
{
    /**
     * @param list<string> $configuredTenants
     */
    public function __construct(
        private readonly RecordMandateCancellationHandler $handler,
        private readonly CommandLocks $locks,
        private readonly array $configuredTenants,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        CommandOptions::addTenant($this, 'Tenant id (default: the only configured tenant)');
        CommandSelectors::addEmployerSelector($this);
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return CommandReports::guard($output, function () use ($input, $output): int {
            $tenant = CommandTenants::tenantOrSingleConfigured($input, $this->configuredTenants);
            $selector = CommandSelectors::employerSelector($input);

            return $this->locks->forEmployer(
                $output,
                $tenant,
                $selector,
                CommandLocks::MANDATE,
                function () use ($output, $tenant, $selector): int {
                    CommandReports::renderFields($output, CommandViews::mandate(($this->handler)(new RecordMandateCancellation($tenant, $selector))));

                    return Command::SUCCESS;
                },
            );
        });
    }
}
