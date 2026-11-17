<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Command;

use ArrayObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Command\CheckInstallationCommand;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Diagnostic\InstallationCheck;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\App\TestKernel;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Support\HttpScript;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Support\Output;

/**
 * One method per check outcome.
 *
 * @mago-expect lint:too-many-methods Each scenario of the command is one test method.
 */
final class CheckInstallationCommandTest extends TestCase
{
    private const NAME = 'urssaf:paje:check';

    public function testEveryCheckIsReportedInOrderAndWarningsDoNotFail(): void
    {
        $harness = Harness::boot(['lock' => ['enabled' => false]]);
        $command = $harness->command(self::NAME);

        static::assertSame(0, $command->execute([]));

        static::assertSame([
            ['configuration', '', 'ok', 'sandbox: https://api-edi.urssaf.fr'],
            ['credentials', 'tenant-1', 'ok', 'Credentials found.'],
            ['authentication', 'tenant-1', 'ok', 'Access token granted by URSSAF.'],
            ['token store', '', 'warning', 'Cache pool "cache.app": declare a dedicated pool shared by every process, so that clearing the application cache does not drop the tokens (docs/symfony.md).'],
            ['persistence', '', 'warning', 'In-memory persistence: every record is lost when the process ends (tests and prototypes only).'],
            ['lock', '', 'warning', 'Disabled: overlapping runs are only protected by the claims (install symfony/lock, see docs/symfony.md).'],
        ], self::rows($command));
        static::assertMatchesRegularExpression('/^\| Check +\| Subject +\| Status +\| Detail +\|$/m', $command->getDisplay());
        static::assertStringContainsString('The URSSAF integration is ready.', $command->getDisplay());
        static::assertStringNotContainsString('check(s) failed', $command->getDisplay());
    }

    public function testTheAuthenticationIsAskedToUrssafOnEveryRunWhateverTheTokenStore(): void
    {
        $harness = Harness::boot();

        $harness->command(self::NAME)->execute([]);
        $harness->command(self::NAME)->execute([]);

        static::assertSame(2, $harness->urssaf->calls(HttpScript::ROUTE_AUTHENTICATION));
    }

    public function testRefusedCredentialsFailTheCheck(): void
    {
        $harness = Harness::boot();
        $harness->urssaf->respond(HttpScript::ROUTE_AUTHENTICATION, 401, '{"error":"invalid_client"}');
        $command = $harness->command(self::NAME);

        static::assertSame(1, $command->execute([]));

        static::assertSame('failure', self::rows($command)[2][2]);
        static::assertStringContainsString('1 check(s) failed.', $command->getDisplay());
        static::assertStringNotContainsString('ready', $command->getDisplay());
    }

    public function testATenantWithoutCredentialsFailsWithoutCallingUrssafForIt(): void
    {
        $harness = Harness::boot();
        $command = $harness->command(self::NAME);

        static::assertSame(1, $command->execute(['--tenant' => 'tenant-9']));

        $rows = self::rows($command);
        static::assertSame(['credentials', 'tenant-9', 'failure'], \array_slice($rows[1], 0, 3));
        static::assertSame(['authentication', 'tenant-9', 'failure'], \array_slice($rows[2], 0, 3));
        static::assertStringContainsString('2 check(s) failed.', $command->getDisplay());
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_AUTHENTICATION));
    }

    public function testSkipRemoteDoesNotCallUrssaf(): void
    {
        $harness = Harness::boot();
        $command = $harness->command(self::NAME);

        static::assertSame(0, $command->execute(['--skip-remote' => true]));

        static::assertSame(['authentication', '', 'skipped', 'Not checked (--skip-remote).'], self::rows($command)[2]);
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_AUTHENTICATION));
    }

    public function testNoTenantToCheckIsAnInvalidUsage(): void
    {
        $command = Harness::boot(['credentials' => null, 'credentials_provider' => 'test.credentials'])->command(self::NAME);

        static::assertSame(2, $command->execute([]));
        static::assertStringContainsString('No tenant to process: use --tenant=<id> or configure "urssaf_tierce_declaration_paje.tenants".', $command->getDisplay());
    }

    public function testTheSimulatedGatewayIsReportedInsteadOfTheAuthentication(): void
    {
        $harness = Harness::boot(['gateway' => ['simulated' => true]]);
        $command = $harness->command(self::NAME);

        static::assertSame(0, $command->execute([]));

        static::assertSame(['gateway', '', 'warning', 'Simulated gateway ("gateway.simulated"): nothing is sent to URSSAF.'], self::rows($command)[2]);
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_AUTHENTICATION));
    }

    public function testADedicatedTokenStoreAndTheLockAreOk(): void
    {
        $pool = self::executedRows(Harness::boot(['token_store' => ['cache_pool' => 'cache.system']], false, true)->command(self::NAME));
        $service = self::executedRows(Harness::boot(['token_store' => ['service' => 'test.token_store']])->command(self::NAME));

        static::assertSame(['token store', '', 'ok', 'Cache pool "cache.system": it must be shared by every process that calls URSSAF.'], $pool[3]);
        static::assertSame(['lock', '', 'ok', 'Enabled: use a store shared by every host that runs the crons and workers.'], $pool[5]);
        static::assertSame(['token store', '', 'ok', 'Service "test.token_store".'], $service[3]);
    }

    public function testACustomPersistenceIsOk(): void
    {
        $rows = self::executedRows(Harness::boot(['persistence' => [
            'driver' => 'custom',
            'employer_account_repository' => 'test.accounts',
            'mandate_repository' => 'test.mandates',
            'declaration_record_repository' => 'test.declarations',
        ]])->command(self::NAME));

        static::assertSame(['persistence', '', 'ok', 'Repositories of the application ("custom" driver).'], $rows[4]);
    }

    public function testAnInvalidEnvironmentAtRunTimeFailsTheConfigurationCheckInsteadOfCrashing(): void
    {
        $_ENV['URSSAF_CHECK_ENVIRONMENT'] = 'staging';
        $_SERVER['URSSAF_CHECK_ENVIRONMENT'] = 'staging';
        try {
            $harness = Harness::boot(['environment' => '%env(URSSAF_CHECK_ENVIRONMENT)%']);
            $command = $harness->command(self::NAME);

            static::assertSame(1, $command->execute([]));
        } finally {
            unset($_ENV['URSSAF_CHECK_ENVIRONMENT'], $_SERVER['URSSAF_CHECK_ENVIRONMENT']);
        }

        $rows = self::rows($command);
        static::assertSame(['configuration', '', 'failure'], \array_slice($rows[0], 0, 3));
        static::assertStringContainsString('staging', $rows[0][3]);
        static::assertSame(['credentials', 'tenant-1', 'ok', 'Credentials found.'], $rows[1]);
        static::assertSame(['authentication', 'tenant-1', 'failure', 'Not checked: the configuration is invalid (see the "configuration" check).'], $rows[2]);
        static::assertStringContainsString('2 check(s) failed.', $command->getDisplay());
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_AUTHENTICATION));
    }

    public function testAnInvalidBaseUriFailsTheConfigurationAndAuthenticationChecks(): void
    {
        $command = Harness::boot(['base_uri' => 'not a uri'])->command(self::NAME);

        static::assertSame(1, $command->execute([]));

        $rows = self::rows($command);
        static::assertSame(['configuration', '', 'failure'], \array_slice($rows[0], 0, 3));
        static::assertSame('Not checked: the configuration is invalid (see the "configuration" check).', $rows[2][3]);
    }

    public function testTheChecksOfTheHostAreAutoconfiguredAndRunAfterThoseOfThePackage(): void
    {
        $container = (new TestKernel())->definitions();

        $autoconfigured = $container->getAutoconfiguredInstanceof();
        static::assertArrayHasKey(InstallationCheck::class, $autoconfigured);
        static::assertSame([InstallationCheck::TAG => [[]]], $autoconfigured[InstallationCheck::class]->getTags());
        $arguments = $container->getDefinition(CheckInstallationCommand::class)->getArguments();
        static::assertEquals([new TaggedIteratorArgument(InstallationCheck::TAG), '%urssaf_tierce_declaration_paje.tenants%'], $arguments);
    }

    public function testEveryCheckRunsInIterationOrderWithTheTenantsAndTheRemoteCallsFlag(): void
    {
        $seen = new ArrayObject();
        $tester = new CommandTester(new CheckInstallationCommand([new RecordingCheck('first', $seen), new RecordingCheck('second', $seen)], ['tenant-1', 'tenant-2']));

        static::assertSame(0, $tester->execute(['--skip-remote' => true]));

        static::assertSame(['first:2:local', 'second:2:local'], $seen->getArrayCopy());
        static::assertSame(['first', 'first+', 'second', 'second+'], array_column(\array_slice(Output::rows($tester->getDisplay()), 1), 0));
    }

    /**
     * The rows of the report of a run with --skip-remote.
     *
     * @return list<list<string>>
     */
    private static function executedRows(CommandTester $command): array
    {
        $command->execute(['--skip-remote' => true]);

        return self::rows($command);
    }

    /**
     * The rows of the report table, without its header.
     *
     * @return list<list<string>>
     */
    private static function rows(CommandTester $command): array
    {
        return \array_slice(Output::rows($command->getDisplay()), 1);
    }
}
