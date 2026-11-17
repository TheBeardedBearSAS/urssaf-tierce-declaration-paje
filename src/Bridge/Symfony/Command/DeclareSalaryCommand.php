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
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\DeclareSalary;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\DeclareSalaryHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\Input\DeclarationFactory;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\ExternalReference;

/**
 * Declares a salary to URSSAF (methods 050 and 051). The declaration comes from a JSON file (--payload), the birth date of
 * the employer from a hidden prompt (ADR 0013); the external reference is the idempotency key (ADR 0003). Exits with 0 only
 * when the declaration is "declared": any other state (rejected, already declared, unknown outcome, invalid credentials,
 * being sent by another process) needs an operator and exits with 1. A declaration whose answer was lost is never resent.
 */
#[AsCommand(name: 'urssaf:paje:declare', description: 'Declare a salary to URSSAF (methods 050 and 051)')]
final class DeclareSalaryCommand extends Command
{
    private const OPTION_REFERENCE = 'reference';
    private const OPTION_PAYLOAD = 'payload';
    private const OPTION_SKIP_MANDATE_CHECK = 'skip-mandate-check';

    /**
     * @param list<string> $configuredTenants
     */
    public function __construct(
        private readonly DeclareSalaryHandler $declare,
        private readonly array $configuredTenants,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        CommandPersonalInputs::addEmployer($this);
        CommandOptions::addTenant($this, 'Tenant id (default: the only configured tenant)');
        $this->addOption(self::OPTION_REFERENCE, null, InputOption::VALUE_REQUIRED, 'Your reference of the declaration, its idempotency key (required)');
        CommandOptions::addActivity($this);
        CommandPayloads::add($this, self::OPTION_PAYLOAD, 'JSON file with the declaration (required): see Domain\Declaration\Input\DeclarationFactory');
        $this->addOption(self::OPTION_SKIP_MANDATE_CHECK, null, InputOption::VALUE_NONE, 'Do not require an active local mandate (when "workflow.require_local_mandate" is on)');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return CommandReports::guard($output, function () use ($input, $output): int {
            $tenant = CommandTenants::tenantOrSingleConfigured($input, $this->configuredTenants);
            $reference = ExternalReference::fromString(CommandOptions::text($input, self::OPTION_REFERENCE) ?? throw new InvalidOptionException('The --reference option is required.'));
            $declaration = (new DeclarationFactory())->fromInput(CommandOptions::activity($input), CommandPayloads::require($input, self::OPTION_PAYLOAD));
            $employer = CommandPersonalInputs::employer($input, $output, new QuestionHelper());

            $submission = ($this->declare)(new DeclareSalary($tenant, $reference, $employer, $declaration, $input->getOption(self::OPTION_SKIP_MANDATE_CHECK) === true));
            CommandReports::renderFields($output, [...CommandViews::declaration($submission->declaration), ['Outcome', $submission->outcome->value]]);

            $state = $submission->declaration->status->state;
            $hint = match ($state) {
                DeclarationState::OutcomeUnknown->value => 'The answer was lost: never send it again. Check on pajemploi.urssaf.fr, then run urssaf:paje:declaration:resolve.',
                DeclarationState::InvalidCredentials->value => 'Fix the credentials, then run urssaf:paje:declarations:requeue-invalid-credentials and declare again.',
                DeclarationState::Rejected->value => 'Fix the data (see the error codes) and declare it under a new reference.',
                DeclarationState::AlreadyDeclared->value => 'URSSAF already holds a declaration for this key: see urssaf:paje:declaration:show.',
                DeclarationState::Submitting->value => 'It is being sent by another process: wait, then run urssaf:paje:declarations:reconcile-stale.',
                default => null,
            };
            if ($hint !== null) {
                $output->writeln(\sprintf('<comment>%s</comment>', $hint));
            }

            return $state === DeclarationState::Declared->value ? Command::SUCCESS : Command::FAILURE;
        });
    }
}
