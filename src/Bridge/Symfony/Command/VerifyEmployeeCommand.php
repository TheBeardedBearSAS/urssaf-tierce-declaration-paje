<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Command;

use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Query\VerifyEmployee;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Query\VerifyEmployeeHandler;

/**
 * Verifies an employee with URSSAF (method 021); writes nothing. The employee is given by number, by NIR (hidden prompt)
 * or by identity (JSON file): ADR 0013.
 */
#[AsCommand(name: 'urssaf:paje:employee:verify', description: 'Verify an employee with URSSAF (method 021); writes nothing')]
final class VerifyEmployeeCommand extends Command
{
    /**
     * @param list<string> $configuredTenants
     */
    public function __construct(
        private readonly VerifyEmployeeHandler $verify,
        private readonly array $configuredTenants,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        CommandPersonalInputs::addEmployee($this);
        CommandOptions::addTenant($this, 'Tenant id (default: the only configured tenant)');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return CommandReports::guard($output, function () use ($input, $output): int {
            $tenant = CommandTenants::tenantOrSingleConfigured($input, $this->configuredTenants);
            $employee = CommandPersonalInputs::employee($input, $output, new QuestionHelper());

            CommandReports::renderFields($output, CommandViews::employeeAccount(($this->verify)(new VerifyEmployee($tenant, $employee))));

            return Command::SUCCESS;
        });
    }
}
