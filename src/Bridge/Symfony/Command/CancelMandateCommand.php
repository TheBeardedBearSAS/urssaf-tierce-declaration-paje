<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Command;

use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\CancelMandate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\CancelMandateHandler;

/**
 * Cancels the mandate of a tracked employer at URSSAF (method 031), after a confirmation (or --force). Runs under the
 * lock of the employer (ADR 0006). When it ends with "mandate.recording_failed", URSSAF accepted the cancellation: never
 * run it again, use urssaf:paje:mandate:record-cancellation.
 */
#[AsCommand(name: 'urssaf:paje:mandate:cancel', description: 'Cancel the mandate of a tracked employer at URSSAF (method 031)')]
final class CancelMandateCommand extends Command
{
    private const OPTION_FORCE = 'force';

    /**
     * @param list<string> $configuredTenants
     */
    public function __construct(
        private readonly CancelMandateHandler $handler,
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
        $this->addOption(self::OPTION_FORCE, null, InputOption::VALUE_NONE, 'Do not ask for a confirmation (required when not interactive)');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return CommandReports::guard($output, function () use ($input, $output): int {
            $tenant = CommandTenants::tenantOrSingleConfigured($input, $this->configuredTenants);
            $selector = CommandSelectors::employerSelector($input);
            if ($input->getOption(self::OPTION_FORCE) !== true) {
                if (!$input->isInteractive()) {
                    throw new InvalidOptionException('Cancelling a mandate needs a confirmation: use --force when not interactive.');
                }
                $question = new ConfirmationQuestion('Cancel the mandate of this employer at URSSAF? [y/N] ', false);
                if ((new QuestionHelper())->ask($input, $output, $question) !== true) {
                    $output->writeln('Aborted: nothing was sent to URSSAF.');

                    return Command::SUCCESS;
                }
            }

            return $this->locks->forEmployer(
                $output,
                $tenant,
                $selector,
                CommandLocks::MANDATE,
                function () use ($output, $tenant, $selector): int {
                    CommandReports::renderFields($output, CommandViews::mandate(($this->handler)(new CancelMandate($tenant, $selector))));

                    return Command::SUCCESS;
                },
            );
        });
    }
}
