<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\DependencyInjection;

use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\CredentialsProvider;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Query\GetWorkflowOverviewHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Command\CheckInstallationCommand;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Command\WorkflowStatusCommand;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Diagnostic\AuthenticationCheck;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Diagnostic\ConfigurationCheck;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Diagnostic\CredentialsCheck;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Diagnostic\InstallationCheck;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Diagnostic\LockCheck;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Diagnostic\PersistenceCheck;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Diagnostic\SimulatedGatewayCheck;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Diagnostic\TokenStoreCheck;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Lock\OperationLocks;

/**
 * The diagnostic commands (ADR 0013, decision 9): "urssaf:paje:check" with its checks, in report order (registration
 * order, host checks afterwards unless they have a positive tag priority), and "urssaf:paje:status" (the workflow overview,
 * only when a statistics port is available).
 *
 * The configuration and the token provider reach their checks through closures, so that an invalid configuration is
 * reported by the "configuration" check instead of preventing the command from running.
 *
 * @phpstan-import-type Config from ServiceRegistrar
 */
final readonly class DiagnosticRegistrar
{
    public function __construct(private ServicesConfigurator $services) {}

    /**
     * @param Config $config
     */
    public function register(array $config): void
    {
        $this->check(ConfigurationCheck::class, [new ServiceClosureArgument(new Reference(ServiceIds::API_CONFIGURATION))]);
        $this->check(CredentialsCheck::class, [new Reference(CredentialsProvider::class)]);
        [$gatewayCheck, $gatewayArguments] = $config['gateway']['simulated']
            ? [SimulatedGatewayCheck::class, []]
            : [AuthenticationCheck::class, [new Reference(CredentialsProvider::class), new ServiceClosureArgument(new Reference(ServiceIds::PROBE_TOKEN_PROVIDER))]];
        $this->check($gatewayCheck, $gatewayArguments);
        $this->check(TokenStoreCheck::class, [$config['token_store']['service'], $config['token_store']['cache_pool']]);
        $this->check(PersistenceCheck::class, [$config['persistence']['driver']]);
        $this->check(LockCheck::class, [new Reference(OperationLocks::class)]);

        $this->services->set(CheckInstallationCommand::class)
            ->args([new TaggedIteratorArgument(ServiceIds::TAG_INSTALLATION_CHECK), '%' . ServiceIds::PARAMETER_TENANTS . '%'])
            ->tag('console.command');
        if (PersistenceRegistrar::hasStatistics($config['persistence']['driver'], $config['persistence']['workflow_statistics'])) {
            $this->services->set(WorkflowStatusCommand::class)->args([new Reference(GetWorkflowOverviewHandler::class)])->tag('console.command');
        }
    }

    /**
     * @param class-string<InstallationCheck> $class
     * @param list<mixed>                     $arguments
     */
    private function check(string $class, array $arguments): void
    {
        $this->services->set($class)->args($arguments)->tag(ServiceIds::TAG_INSTALLATION_CHECK);
    }
}
