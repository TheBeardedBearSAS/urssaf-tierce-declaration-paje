<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Command;

use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\DeclarationView;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Query\FindDeclarations;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Query\FindDeclarationsHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\ExternalReference;

/**
 * Lists the local declaration records of a tenant: those in a state (the oldest first, capped by the workflow policy), or
 * those given by external reference or record id. Local read, no URSSAF call.
 */
#[AsCommand(name: 'urssaf:paje:declarations:list', description: 'List the local declaration records (no URSSAF call)')]
final class ListDeclarationsCommand extends Command
{
    private const OPTION_STATE = 'state';
    private const OPTION_REFERENCE = 'declaration';
    private const OPTION_RECORD_ID = 'record-id';

    /**
     * @param list<string> $configuredTenants
     */
    public function __construct(
        private readonly FindDeclarationsHandler $find,
        private readonly array $configuredTenants,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        CommandOptions::addTenant($this, 'Tenant id (default: the only configured tenant)');
        $this->addOption(self::OPTION_STATE, null, InputOption::VALUE_REQUIRED, 'List the declarations in this state, e.g. outcome_unknown');
        $this->addOption(self::OPTION_REFERENCE, null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'List this declaration, by external reference (repeatable)');
        $this->addOption(self::OPTION_RECORD_ID, null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'List this declaration, by record id (repeatable)');
        CommandOptions::addLimit($this);
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return CommandReports::guard($output, function () use ($input, $output): int {
            $tenant = CommandTenants::tenantOrSingleConfigured($input, $this->configuredTenants);
            $state = CommandOptions::text($input, self::OPTION_STATE);
            $references = CommandOptions::texts($input, self::OPTION_REFERENCE);
            $recordIds = CommandOptions::texts($input, self::OPTION_RECORD_ID);
            $limit = CommandOptions::limit($input);
            if (\count(array_filter([$state !== null, $references !== [], $recordIds !== []])) !== 1) {
                throw new InvalidOptionException('Give exactly one of --state, --declaration or --record-id.');
            }
            if ($limit !== null && $state === null) {
                throw new InvalidOptionException('--limit only applies with --state.');
            }

            $query = match (true) {
                $state !== null => FindDeclarations::inState($tenant, DeclarationState::tryFrom($state) ?? throw new InvalidOptionException('The --state option must be a declaration state, e.g. outcome_unknown.'), $limit),
                $references !== [] => FindDeclarations::byExternalReferences($tenant, ...array_map(ExternalReference::fromString(...), $references)),
                default => FindDeclarations::byRecordIds($tenant, ...array_map(DeclarationRecordId::fromString(...), $recordIds)),
            };
            $views = ($this->find)($query);
            $table = new Table($output);
            $table->setHeaders(['Record id', 'External reference', 'State', 'Attempts', 'Updated at']);
            $table->setRows(array_map(
                static fn(DeclarationView $view): array => [$view->recordId, $view->externalReference, $view->status->state, $view->status->attempts, $view->status->updatedAt],
                $views,
            ));
            $table->render();
            $output->writeln(\sprintf('%d declaration(s).', \count($views)));

            return Command::SUCCESS;
        });
    }
}
