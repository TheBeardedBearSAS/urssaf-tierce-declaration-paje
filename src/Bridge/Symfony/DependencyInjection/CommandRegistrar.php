<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\DependencyInjection;

use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Command\AssociateEmployeeCommand;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Command\CancelMandateCommand;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Command\CommandLocks;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Command\DeclareSalaryCommand;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Command\EstimateContributionsCommand;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Command\ForgetEmployerCommand;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Command\ImportEmployerCommand;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Command\ImportMandateCommand;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Command\ListDeclarationsCommand;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Command\PreDeclareSalaryCommand;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Command\PurgeOutboxCommand;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Command\ReconcileStaleDeclarationsCommand;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Command\RecordMandateCancellationCommand;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Command\RegisterMandateCommand;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Command\RelayOutboxCommand;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Command\RequeueInvalidCredentialsCommand;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Command\ResolveDeclarationOutcomeCommand;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Command\ShowDeclarationCommand;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Command\SyncEmployerStatusesCommand;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Command\TrackEmployerCommand;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Command\VerifyChildrenCommand;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Command\VerifyEmployeeCommand;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Command\VerifyEmployerCommand;

/**
 * The console commands (`urssaf:paje:...`): lazy services tagged "console.command". The outbox commands exist only with
 * the outbox, as their handlers do; the commands that work for one tenant fall back on the configured tenants.
 *
 * @phpstan-import-type Config from ServiceRegistrar
 */
final readonly class CommandRegistrar
{
    /** Commands that fall back on the configured tenants. */
    private const TENANT_COMMANDS = [
        TrackEmployerCommand::class,
        ImportEmployerCommand::class,
        VerifyEmployerCommand::class,
        ForgetEmployerCommand::class,
        RegisterMandateCommand::class,
        ImportMandateCommand::class,
        CancelMandateCommand::class,
        RecordMandateCancellationCommand::class,
        VerifyEmployeeCommand::class,
        AssociateEmployeeCommand::class,
        VerifyChildrenCommand::class,
        EstimateContributionsCommand::class,
        PreDeclareSalaryCommand::class,
        DeclareSalaryCommand::class,
        ResolveDeclarationOutcomeCommand::class,
        RequeueInvalidCredentialsCommand::class,
        ShowDeclarationCommand::class,
        ListDeclarationsCommand::class,
    ];

    private const OUTBOX_COMMANDS = [
        RelayOutboxCommand::class,
        PurgeOutboxCommand::class,
    ];

    public function __construct(private ServicesConfigurator $services) {}

    /**
     * @param Config $config
     */
    public function register(array $config): void
    {
        // The reconciliation works for every tenant; the outbox commands exist with the outbox.
        $commands = $config['outbox']['enabled'] ? [ReconcileStaleDeclarationsCommand::class, ...self::OUTBOX_COMMANDS] : [ReconcileStaleDeclarationsCommand::class];
        $this->services->set(CommandLocks::class)->autowire();
        foreach ($commands as $command) {
            $this->services->set($command)->autowire()->tag('console.command');
        }
        foreach (self::TENANT_COMMANDS as $command) {
            $this->services->set($command)
                ->autowire()
                ->arg('$configuredTenants', '%' . ServiceIds::PARAMETER_TENANTS . '%')
                ->tag('console.command');
        }
        $this->services->set(SyncEmployerStatusesCommand::class)
            ->autowire()
            ->arg('$bus', $config['messenger']['enabled'] ? new Reference($config['messenger']['bus']) : null)
            ->arg('$configuredTenants', '%' . ServiceIds::PARAMETER_TENANTS . '%')
            ->tag('console.command');
    }
}
