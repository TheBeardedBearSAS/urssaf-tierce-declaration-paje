<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Diagnostic;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Credentials;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\DependencyInjection\ServiceIds;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Diagnostic\AuthenticationCheck;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Diagnostic\CheckContext;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Diagnostic\CheckResult;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Diagnostic\CheckStatus;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Diagnostic\ConfigurationCheck;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Diagnostic\CredentialsCheck;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Diagnostic\LockCheck;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Diagnostic\PersistenceCheck;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Diagnostic\SimulatedGatewayCheck;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Diagnostic\TokenStoreCheck;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Lock\OperationLocks;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Credentials\StaticCredentialsProvider;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Environment;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Http\OAuth2TokenProvider;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Support\HttpScript;

/**
 * Every check of the installation, one method per outcome (ADR 0013, decision 9).
 *
 * @mago-expect lint:too-many-methods Each outcome of each check is one test method.
 */
final class ChecksTest extends TestCase
{
    public function testTheContextTellsWhetherUrssafMayBeCalled(): void
    {
        $tenants = [TenantId::fromString('tenant-1')];

        $remote = CheckContext::withRemoteCalls($tenants);
        $local = CheckContext::withoutRemoteCalls($tenants);

        static::assertTrue($remote->remoteCallsAllowed);
        static::assertFalse($local->remoteCallsAllowed);
        static::assertEquals($tenants, $remote->tenants);
        static::assertEquals($tenants, $local->tenants);
    }

    public function testEveryStatusHasItsWord(): void
    {
        static::assertSame(['ok', 'warning', 'failure', 'skipped'], array_map(static fn(CheckStatus $status): string => $status->value, CheckStatus::cases()));
    }

    public function testACredentialsCheckReportsEveryTenant(): void
    {
        $check = new CredentialsCheck(new StaticCredentialsProvider(['tenant-1' => Credentials::of('client-1', 'secret-1')]));

        $results = $check->run(CheckContext::withoutRemoteCalls([TenantId::fromString('tenant-1'), TenantId::fromString('tenant-2')]));

        static::assertEquals([
            new CheckResult('credentials', 'tenant-1', CheckStatus::Ok, 'Credentials found.'),
            new CheckResult('credentials', 'tenant-2', CheckStatus::Failure, $results[1]->detail),
        ], $results);
        static::assertStringContainsString('tenant-2', $results[1]->detail);
        static::assertStringNotContainsString('secret-1', $results[1]->detail);
    }

    public function testTheSimulatedGatewayIsAWarning(): void
    {
        $results = (new SimulatedGatewayCheck())->run(self::context());

        static::assertEquals([new CheckResult('gateway', '', CheckStatus::Warning, 'Simulated gateway ("gateway.simulated"): nothing is sent to URSSAF.')], $results);
    }

    public function testTheLockIsOkOnlyWhenEnabled(): void
    {
        $enabled = (new LockCheck(new OperationLocks(new LockFactory(new InMemoryStore()))))->run(self::context());
        $disabled = (new LockCheck(new OperationLocks(null)))->run(self::context());

        static::assertEquals([new CheckResult('lock', '', CheckStatus::Ok, 'Enabled: use a store shared by every host that runs the crons and workers.')], $enabled);
        static::assertEquals([new CheckResult('lock', '', CheckStatus::Warning, 'Disabled: overlapping runs are only protected by the claims (install symfony/lock, see docs/symfony.md).')], $disabled);
    }

    public function testTheTokenStoreIsReportedByServiceThenByPool(): void
    {
        $service = (new TokenStoreCheck('app.token_store', 'cache.app'))->run(self::context());
        $shared = (new TokenStoreCheck(null, 'cache.app'))->run(self::context());
        $dedicated = (new TokenStoreCheck(null, 'urssaf_paje.token_cache'))->run(self::context());

        static::assertEquals([new CheckResult('token store', '', CheckStatus::Ok, 'Service "app.token_store".')], $service);
        static::assertSame(CheckStatus::Warning, $shared[0]->status);
        static::assertStringContainsString('declare a dedicated pool shared by every process', $shared[0]->detail);
        static::assertEquals([new CheckResult('token store', '', CheckStatus::Ok, 'Cache pool "urssaf_paje.token_cache": it must be shared by every process that calls URSSAF.')], $dedicated);
    }

    public function testThePersistenceDriverIsReported(): void
    {
        $inMemory = (new PersistenceCheck('in_memory'))->run(self::context());
        $custom = (new PersistenceCheck('custom'))->run(self::context());

        static::assertEquals([new CheckResult('persistence', '', CheckStatus::Warning, 'In-memory persistence: every record is lost when the process ends (tests and prototypes only).')], $inMemory);
        static::assertEquals([new CheckResult('persistence', '', CheckStatus::Ok, 'Repositories of the application ("custom" driver).')], $custom);
    }

    public function testTheConfigurationIsReportedWithItsEnvironmentAndBaseUri(): void
    {
        $check = new ConfigurationCheck(static fn(): ApiConfiguration => ApiConfiguration::forEnvironment(Environment::Production));

        static::assertEquals(
            [new CheckResult('configuration', '', CheckStatus::Ok, \sprintf('production: %s', Environment::Production->baseUri()))],
            $check->run(self::context()),
        );
    }

    public function testTheAuthenticationIsSkippedWithoutRemoteCalls(): void
    {
        $harness = Harness::boot();
        $check = self::authentication($harness);

        $results = $check->run(CheckContext::withoutRemoteCalls([TenantId::fromString('tenant-1')]));

        static::assertEquals([new CheckResult('authentication', '', CheckStatus::Skipped, 'Not checked (--skip-remote).')], $results);
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_AUTHENTICATION));
    }

    public function testTheAuthenticationAsksAFreshTokenForEveryTenant(): void
    {
        // @mago-expect lint:no-literal-password The secret is an environment placeholder, set by the test kernel.
        $harness = Harness::boot(['credentials' => ['tenant-2' => ['client_id' => 'client-2', 'client_secret' => '%env(URSSAF_TEST_SECRET)%']]]);
        $check = self::authentication($harness);
        $context = CheckContext::withRemoteCalls([TenantId::fromString('tenant-1'), TenantId::fromString('tenant-2')]);

        $first = $check->run($context);
        $second = $check->run($context);

        static::assertEquals([
            new CheckResult('authentication', 'tenant-1', CheckStatus::Ok, 'Access token granted by URSSAF.'),
            new CheckResult('authentication', 'tenant-2', CheckStatus::Ok, 'Access token granted by URSSAF.'),
        ], $first);
        static::assertEquals($first, $second);
        static::assertSame(4, $harness->urssaf->calls(HttpScript::ROUTE_AUTHENTICATION));
    }

    public function testRefusedCredentialsAreAFailureOfTheTenant(): void
    {
        $harness = Harness::boot();
        $harness->urssaf->respond(HttpScript::ROUTE_AUTHENTICATION, 401, '{"error":"invalid_client"}');

        $results = self::authentication($harness)->run(CheckContext::withRemoteCalls([TenantId::fromString('tenant-1')]));

        static::assertSame(CheckStatus::Failure, $results[0]->status);
        static::assertSame('tenant-1', $results[0]->subject);
        static::assertStringContainsString('401', $results[0]->detail);
        static::assertStringNotContainsString('secret', $results[0]->detail);
    }

    public function testATenantWithoutCredentialsIsAFailureWithoutCallingUrssaf(): void
    {
        $harness = Harness::boot();

        $results = self::authentication($harness)->run(CheckContext::withRemoteCalls([TenantId::fromString('tenant-9')]));

        static::assertSame(CheckStatus::Failure, $results[0]->status);
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_AUTHENTICATION));
    }

    private static function authentication(Harness $harness): AuthenticationCheck
    {
        $tokens = $harness->privateService(ServiceIds::PROBE_TOKEN_PROVIDER, OAuth2TokenProvider::class);

        return new AuthenticationCheck(
            new StaticCredentialsProvider([
                'tenant-1' => Credentials::of('client-1', 'secret-1'),
                'tenant-2' => Credentials::of('client-2', 'secret-2'),
            ]),
            static fn(): OAuth2TokenProvider => $tokens,
        );
    }

    private static function context(): CheckContext
    {
        return CheckContext::withRemoteCalls([TenantId::fromString('tenant-1')]);
    }
}
