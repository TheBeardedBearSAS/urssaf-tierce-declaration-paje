<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Command;

use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Query\PreDeclareSalary;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Query\PreDeclareSalaryHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\Input\DeclarationFactory;

/**
 * Checks a salary declaration with URSSAF without recording it (methods 045 and 046); nothing is declared nor stored. The
 * declaration comes from a JSON file (--payload), the birth date of the employer from a hidden prompt (ADR 0013).
 */
#[AsCommand(name: 'urssaf:paje:predeclare', description: 'Check a salary declaration with URSSAF without declaring it (methods 045 and 046)')]
final class PreDeclareSalaryCommand extends Command
{
    private const OPTION_PAYLOAD = 'payload';
    private const OPTION_SKIP_MANDATE_CHECK = 'skip-mandate-check';

    /**
     * @param list<string> $configuredTenants
     */
    public function __construct(
        private readonly PreDeclareSalaryHandler $predeclare,
        private readonly array $configuredTenants,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        CommandPersonalInputs::addEmployer($this);
        CommandOptions::addTenant($this, 'Tenant id (default: the only configured tenant)');
        CommandOptions::addActivity($this);
        CommandPayloads::add($this, self::OPTION_PAYLOAD, 'JSON file with the declaration (required): see Domain\Declaration\Input\DeclarationFactory');
        $this->addOption(self::OPTION_SKIP_MANDATE_CHECK, null, InputOption::VALUE_NONE, 'Do not require an active local mandate (when "workflow.require_local_mandate" is on)');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return CommandReports::guard($output, function () use ($input, $output): int {
            $tenant = CommandTenants::tenantOrSingleConfigured($input, $this->configuredTenants);
            $declaration = (new DeclarationFactory())->fromInput(CommandOptions::activity($input), CommandPayloads::require($input, self::OPTION_PAYLOAD));
            $employer = CommandPersonalInputs::employer($input, $output, new QuestionHelper());

            CommandReports::renderFields($output, CommandViews::preDeclaration(($this->predeclare)(
                new PreDeclareSalary($tenant, $employer, $declaration, $input->getOption(self::OPTION_SKIP_MANDATE_CHECK) === true),
            )));

            return Command::SUCCESS;
        });
    }
}
