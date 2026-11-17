<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Command;

use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\TenantOverview;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Query\GetWorkflowOverview;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Query\GetWorkflowOverviewHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerStage;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateState;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;

/**
 * Prints the employers, mandates and declarations by tenant and state (table, or JSON for monitoring). Reads the local
 * store only: URSSAF is not called.
 */
#[AsCommand(name: 'urssaf:paje:status', description: 'Count the employers, mandates and declarations by tenant and state')]
final class WorkflowStatusCommand extends Command
{
    private const OPTION_FORMAT = 'format';
    private const FORMAT_TABLE = 'table';
    private const FORMAT_JSON = 'json';

    public function __construct(private readonly GetWorkflowOverviewHandler $overview)
    {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        CommandOptions::addTenant($this, 'Tenant id (default: every tenant with records)');
        $this->addOption(self::OPTION_FORMAT, null, InputOption::VALUE_REQUIRED, 'Output format: table or json', self::FORMAT_TABLE);
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return CommandReports::guard($output, function () use ($input, $output): int {
            $format = CommandOptions::text($input, self::OPTION_FORMAT);
            if ($format !== self::FORMAT_TABLE && $format !== self::FORMAT_JSON) {
                throw new InvalidOptionException('The --format option must be "table" or "json".');
            }
            $tenant = CommandOptions::tenant($input);
            $overviews = ($this->overview)($tenant instanceof TenantId ? GetWorkflowOverview::forTenant($tenant) : GetWorkflowOverview::allTenants());
            if ($format === self::FORMAT_JSON) {
                $output->writeln(json_encode(array_map(static fn(TenantOverview $overview): array => $overview->toArray(), $overviews), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

                return Command::SUCCESS;
            }
            self::render($output, 'Employers', array_map(static fn(EmployerStage $stage): string => $stage->value, EmployerStage::cases()), array_map(
                static fn(TenantOverview $overview): array => [$overview->tenantId, ...$overview->employers],
                $overviews,
            ));
            self::render($output, 'Mandates', array_map(static fn(MandateState $state): string => $state->value, MandateState::cases()), array_map(
                static fn(TenantOverview $overview): array => [$overview->tenantId, ...$overview->mandates],
                $overviews,
            ));
            self::render($output, 'Declarations', [...array_map(static fn(DeclarationState $state): string => $state->value, DeclarationState::cases()), 'Needing attention'], array_map(
                static fn(TenantOverview $overview): array => [$overview->tenantId, ...$overview->declarations, $overview->needingAttention()],
                $overviews,
            ));

            return Command::SUCCESS;
        });
    }

    /**
     * @param list<string>            $columns the headers after the tenant, in the order of the counts of the rows
     * @param list<array<int|string>> $rows    tenant id, then one count per column
     */
    private static function render(OutputInterface $output, string $title, array $columns, array $rows): void
    {
        $output->writeln($title);
        $table = new Table($output);
        $table->setHeaders(['Tenant', ...$columns]);
        $table->setRows($rows);
        $table->render();
    }
}
