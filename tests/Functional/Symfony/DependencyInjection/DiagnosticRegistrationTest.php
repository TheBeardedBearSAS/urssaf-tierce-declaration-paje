<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Reference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\CredentialsProvider;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Query\GetWorkflowOverviewHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Command\CheckInstallationCommand;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Command\WorkflowStatusCommand;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\DependencyInjection\ServiceIds;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Diagnostic\AuthenticationCheck;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Diagnostic\ConfigurationCheck;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Diagnostic\CredentialsCheck;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Diagnostic\LockCheck;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Diagnostic\PersistenceCheck;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Diagnostic\SimulatedGatewayCheck;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Diagnostic\TokenStoreCheck;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Lock\OperationLocks;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\App\TestKernel;

/**
 * The diagnostic commands and the checks they run (ADR 0013, decision 9), as declared by the bundle.
 */
final class DiagnosticRegistrationTest extends TestCase
{
    public function testTheChecksAreRegisteredInReportOrderWithTheTag(): void
    {
        $container = (new TestKernel())->definitions();

        $tagged = array_keys($container->findTaggedServiceIds(ServiceIds::TAG_INSTALLATION_CHECK));

        static::assertSame(
            [ConfigurationCheck::class, CredentialsCheck::class, AuthenticationCheck::class, TokenStoreCheck::class, PersistenceCheck::class, LockCheck::class],
            $tagged,
        );
    }

    public function testTheChecksReceiveTheirCollaborators(): void
    {
        $container = (new TestKernel(['token_store' => ['service' => 'test.token_store', 'cache_pool' => 'cache.system']]))->definitions();

        static::assertEquals([new ServiceClosureArgument(new Reference(ServiceIds::API_CONFIGURATION))], $container->getDefinition(ConfigurationCheck::class)->getArguments());
        static::assertEquals([new Reference(CredentialsProvider::class)], $container->getDefinition(CredentialsCheck::class)->getArguments());
        static::assertEquals(
            [new Reference(CredentialsProvider::class), new ServiceClosureArgument(new Reference(ServiceIds::PROBE_TOKEN_PROVIDER))],
            $container->getDefinition(AuthenticationCheck::class)->getArguments(),
        );
        static::assertSame(['test.token_store', 'cache.system'], $container->getDefinition(TokenStoreCheck::class)->getArguments());
        static::assertSame(['in_memory'], $container->getDefinition(PersistenceCheck::class)->getArguments());
        static::assertEquals([new Reference(OperationLocks::class)], $container->getDefinition(LockCheck::class)->getArguments());
    }

    public function testTheSimulatedGatewayReplacesTheAuthenticationCheck(): void
    {
        $container = (new TestKernel(['gateway' => ['simulated' => true]]))->definitions();

        static::assertTrue($container->hasDefinition(SimulatedGatewayCheck::class));
        static::assertFalse($container->hasDefinition(AuthenticationCheck::class));
    }

    public function testTheCommandsAreConsoleCommandsOfTheBundle(): void
    {
        $container = (new TestKernel())->definitions();

        static::assertTrue($container->getDefinition(CheckInstallationCommand::class)->hasTag('console.command'));
        static::assertTrue($container->getDefinition(WorkflowStatusCommand::class)->hasTag('console.command'));
        static::assertEquals([new Reference(GetWorkflowOverviewHandler::class)], $container->getDefinition(WorkflowStatusCommand::class)->getArguments());
    }

    public function testTheStatusCommandNeedsTheStatisticsOfACustomDriver(): void
    {
        $custom = [
            'driver' => 'custom',
            'employer_account_repository' => 'test.accounts',
            'mandate_repository' => 'test.mandates',
            'declaration_record_repository' => 'test.declarations',
            'transaction_manager' => 'test.transactions',
        ];

        $without = (new TestKernel(['persistence' => $custom]))->definitions();
        $with = (new TestKernel(['persistence' => [...$custom, 'workflow_statistics' => 'test.statistics']]))->definitions();

        static::assertFalse($without->hasDefinition(WorkflowStatusCommand::class));
        static::assertTrue($with->hasDefinition(WorkflowStatusCommand::class));
        static::assertTrue($without->hasDefinition(CheckInstallationCommand::class));
    }
}
