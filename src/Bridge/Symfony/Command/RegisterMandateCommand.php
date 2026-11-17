<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Command;

use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\RegisterMandate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\RegisterMandateHandler;

/**
 * Notifies URSSAF of the mandate of a tracked employer (method 030). Runs under the lock of the employer (ADR 0006). When it ends with "mandate.recording_failed", URSSAF accepted the mandate: never run it again, use urssaf:paje:mandate:import.
 */
#[AsCommand(name: 'urssaf:paje:mandate:register', description: 'Notify URSSAF of the mandate of a tracked employer (method 030)')]
final class RegisterMandateCommand extends Command
{
    /**
     * @param list<string> $configuredTenants
     */
    public function __construct(
        private readonly RegisterMandateHandler $handler,
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
                    CommandReports::renderFields($output, CommandViews::mandate(($this->handler)(new RegisterMandate($tenant, $selector))));

                    return Command::SUCCESS;
                },
            );
        });
    }
}
