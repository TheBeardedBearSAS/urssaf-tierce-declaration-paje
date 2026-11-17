<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Command;

use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Diagnostic\CheckContext;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Diagnostic\CheckResult;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Diagnostic\CheckStatus;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Diagnostic\InstallationCheck;

/**
 * Checks the installation (configuration, credentials, OAuth2 authentication, token store, persistence, lock) and exits
 * with 1 when a check fails: suitable as a deployment step.
 */
#[AsCommand(name: 'urssaf:paje:check', description: 'Check the URSSAF PAJE integration: configuration, credentials, authentication, token store, persistence, lock')]
final class CheckInstallationCommand extends Command
{
    private const OPTION_SKIP_REMOTE = 'skip-remote';

    /**
     * @param iterable<InstallationCheck> $checks
     * @param list<string>                $configuredTenants
     */
    public function __construct(private readonly iterable $checks, private readonly array $configuredTenants)
    {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        CommandOptions::addTenant($this, 'Tenant to check (default: the configured tenants)');
        $this->addOption(self::OPTION_SKIP_REMOTE, null, InputOption::VALUE_NONE, 'Do not call URSSAF (no authentication check)');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return CommandReports::guard($output, function () use ($input, $output): int {
            $tenants = CommandTenants::tenantsOrConfigured($input, $this->configuredTenants);
            $context = $input->getOption(self::OPTION_SKIP_REMOTE) === true ? CheckContext::withoutRemoteCalls($tenants) : CheckContext::withRemoteCalls($tenants);
            $results = [];
            foreach ($this->checks as $check) {
                array_push($results, ...$check->run($context));
            }
            $table = new Table($output);
            $table->setHeaders(['Check', 'Subject', 'Status', 'Detail']);
            $table->setRows(array_map(
                static fn(CheckResult $result): array => [$result->check, $result->subject, $result->status->value, $result->detail],
                $results,
            ));
            $table->render();
            $failures = \count(array_filter($results, static fn(CheckResult $result): bool => $result->status === CheckStatus::Failure));
            if ($failures > 0) {
                $output->writeln(\sprintf('<error>%d check(s) failed.</error>', $failures));

                return Command::FAILURE;
            }
            $output->writeln('<info>The URSSAF integration is ready.</info>');

            return Command::SUCCESS;
        });
    }
}
