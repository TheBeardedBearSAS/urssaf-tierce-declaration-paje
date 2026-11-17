<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Command;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Lock\OperationLocks;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Message\SyncEmployerStatusesMessage;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Support\HeldLocks;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Support\HttpScript;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Support\Output;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Support\Scenario;

/**
 * @mago-expect lint:too-many-methods
 */
final class SyncEmployerStatusesCommandTest extends TestCase
{
    private const NAME = 'urssaf:paje:employers:sync-status';
    private const MESSENGER = ['messenger' => ['enabled' => true]];

    public function testTheEmployersOfEveryTenantAreRefreshed(): void
    {
        $harness = Harness::boot(Accounts::withSecondTenant());
        $first = Scenario::importedEmployer($harness);
        $second = Scenario::importedEmployer($harness, Accounts::SECOND_TENANT, 'employer-2');
        $command = $harness->command(self::NAME);

        static::assertSame(0, $command->execute([]));

        $display = $command->getDisplay();
        static::assertStringContainsString($first->accountId, $display);
        static::assertStringContainsString($second->accountId, $display);
        static::assertStringContainsString('2 record(s) processed (changed: 2).', $display);
        static::assertSame(2, $harness->urssaf->calls(HttpScript::ROUTE_020));
    }

    public function testTheTenantOptionRestrictsTheRun(): void
    {
        $harness = Harness::boot(Accounts::withSecondTenant());
        $first = Scenario::importedEmployer($harness);
        $second = Scenario::importedEmployer($harness, Accounts::SECOND_TENANT, 'employer-2');
        $command = $harness->command(self::NAME);

        static::assertSame(0, $command->execute(['--tenant' => Accounts::SECOND_TENANT]));

        static::assertStringContainsString($second->accountId, $command->getDisplay());
        static::assertStringNotContainsString($first->accountId, $command->getDisplay());
        static::assertSame(1, $harness->urssaf->calls(HttpScript::ROUTE_020));
    }

    public function testTheLimitCapsTheRun(): void
    {
        $harness = Harness::boot();
        Scenario::importedEmployer($harness);
        Accounts::importOtherEmployer($harness, Scenario::TENANT, 'employer-2');
        $command = $harness->command(self::NAME);

        static::assertSame(0, $command->execute(['--limit' => '1']));

        static::assertSame(1, $harness->urssaf->calls(HttpScript::ROUTE_020));
    }

    public function testAnUnchangedEmployerIsReportedAsSuch(): void
    {
        $harness = Harness::boot();
        Scenario::importedEmployer($harness);
        static::assertSame(0, $harness->command(self::NAME)->execute([]));
        $command = $harness->command(self::NAME);

        static::assertSame(0, $command->execute([]));

        static::assertStringContainsString('1 record(s) processed (unchanged: 1).', $command->getDisplay());
    }

    public function testNothingToRefreshIsASuccess(): void
    {
        $command = Harness::boot()->command(self::NAME);

        static::assertSame(0, $command->execute([]));

        static::assertStringContainsString('0 record(s) processed.', $command->getDisplay());
    }

    public function testARefusalOfUrssafIsReportedAndExitsWithOne(): void
    {
        $harness = Harness::boot();
        Scenario::importedEmployer($harness);
        $harness->urssaf->respondWithFixture(HttpScript::ROUTE_020, 400, 'error_pe_inexistant.json');
        $command = $harness->command(self::NAME);

        static::assertSame(1, $command->execute([]));

        static::assertStringContainsString('failed: 1', $command->getDisplay());
    }

    public function testAnUnavailablePlatformIsRetriedLaterWithoutFailing(): void
    {
        $harness = Harness::boot();
        Scenario::importedEmployer($harness);
        $harness->urssaf->respond(HttpScript::ROUTE_020, 503, '{}');
        $command = $harness->command(self::NAME);

        static::assertSame(0, $command->execute([]));

        static::assertStringContainsString('retry_later: 1', $command->getDisplay());
    }

    public function testAnAuthenticationFailureExitsWithOne(): void
    {
        $harness = Harness::boot();
        Scenario::importedEmployer($harness);
        $harness->urssaf->respond(HttpScript::ROUTE_AUTHENTICATION, 401, '{"error":"invalid_client"}');
        $command = $harness->command(self::NAME);

        static::assertSame(1, $command->execute([]));
    }

    /**
     * @param array<string, string> $selector
     */
    #[DataProvider('selectors')]
    public function testOneEmployerIsRefreshedWhateverTheSelector(array $selector): void
    {
        $harness = Harness::boot();
        $account = Scenario::importedEmployer($harness);
        $command = $harness->command(self::NAME);

        static::assertSame(0, $command->execute(array_map(static fn(string $value): string => $value === '{account}' ? $account->accountId : $value, $selector) + ['--tenant' => Scenario::TENANT]));

        static::assertSame([
            'Account id' => $account->accountId,
            'External reference' => Scenario::EMPLOYER,
            'Stage' => 'tracked',
            'Authorised' => 'yes',
            'Online account' => 'Actif',
            'Mandate state' => '-',
            'Last checked at' => '2026-11-15T10:00:00Z',
        ], Fields::of($command->getDisplay()));
        static::assertSame(1, $harness->urssaf->calls(HttpScript::ROUTE_020));
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function selectors(): iterable
    {
        yield 'external reference' => [['--employer' => Scenario::EMPLOYER]];
        yield 'Pajemploi number' => [['--employer-number' => 'Y1869005780009']];
        yield 'account id' => [['--account-id' => '{account}']];
    }

    public function testTheMandateStateIsShownForAMandatedEmployer(): void
    {
        $harness = Harness::boot();
        Scenario::mandatedEmployer($harness);
        $command = $harness->command(self::NAME);

        static::assertSame(0, $command->execute(['--employer' => Scenario::EMPLOYER, '--tenant' => Scenario::TENANT]));

        static::assertSame('registered', Fields::of($command->getDisplay())['Mandate state']);
        static::assertSame('mandate_registered', Fields::of($command->getDisplay())['Stage']);
    }

    /**
     * @param array<string, mixed> $input
     */
    #[DataProvider('invalidUsages')]
    public function testAnInvalidUsageExitsWithTwoAndNeverCallsUrssaf(array $input, string $message): void
    {
        $harness = Harness::boot(self::MESSENGER);
        Scenario::importedEmployer($harness);
        $command = $harness->command(self::NAME);

        static::assertSame(2, $command->execute($input));

        static::assertStringContainsString($message, $command->getDisplay());
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_020));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidUsages(): iterable
    {
        yield 'employer with limit' => [['--employer' => Scenario::EMPLOYER, '--tenant' => 'tenant-1', '--limit' => '1'], 'An employer selector cannot be combined with --limit, --dry-run or --async.'];
        yield 'employer with dry run' => [['--employer' => Scenario::EMPLOYER, '--tenant' => 'tenant-1', '--dry-run' => true], 'An employer selector cannot be combined with --limit, --dry-run or --async.'];
        yield 'employer with async' => [['--employer' => Scenario::EMPLOYER, '--tenant' => 'tenant-1', '--async' => true], 'An employer selector cannot be combined with --limit, --dry-run or --async.'];
        yield 'two selectors' => [['--employer' => Scenario::EMPLOYER, '--account-id' => 'a'], 'Give one of --employer, --employer-number or --account-id.'];
        yield 'invalid limit' => [['--limit' => '0'], 'The --limit option must be a positive integer.'];
        yield 'async with dry run' => [['--async' => true, '--dry-run' => true], '--async cannot be combined with --dry-run.'];
    }

    public function testTheOnlyConfiguredTenantIsUsedWithAnEmployerSelector(): void
    {
        $harness = Harness::boot();
        $account = Scenario::importedEmployer($harness);
        $command = $harness->command(self::NAME);

        static::assertSame(0, $command->execute(['--employer' => Scenario::EMPLOYER]));

        static::assertStringContainsString($account->accountId, $command->getDisplay());
        static::assertSame(1, $harness->urssaf->calls(HttpScript::ROUTE_020));
    }

    public function testAnEmployerSelectorNeedsTheTenantWithSeveralConfiguredTenants(): void
    {
        $harness = Harness::boot(Accounts::withSecondTenant());
        Scenario::importedEmployer($harness);
        $command = $harness->command(self::NAME);

        static::assertSame(2, $command->execute(['--employer' => Scenario::EMPLOYER]));

        static::assertStringContainsString('The --tenant option is required (configured tenants: 2).', $command->getDisplay());
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_020));
    }

    public function testAnUnknownEmployerExitsWithOne(): void
    {
        $command = Harness::boot()->command(self::NAME);

        static::assertSame(1, $command->execute(['--employer' => 'unknown', '--tenant' => Scenario::TENANT]));
    }

    public function testADryRunListsWhatWouldBeRefreshed(): void
    {
        $harness = Harness::boot(Accounts::withSecondTenant());
        $first = Scenario::importedEmployer($harness);
        $second = Scenario::importedEmployer($harness, Accounts::SECOND_TENANT, 'employer-2');
        $command = $harness->command(self::NAME);

        static::assertSame(0, $command->execute(['--dry-run' => true]));

        $rows = Output::rows($command->getDisplay());
        static::assertContains(['tenant-1', $first->accountId], $rows);
        static::assertContains([Accounts::SECOND_TENANT, $second->accountId], $rows);
        static::assertStringContainsString('Dry run: 2 record(s) would be processed, URSSAF was not called.', $command->getDisplay());
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_020));
    }

    public function testTheDryRunHonoursTheTenantAndTheLimit(): void
    {
        $harness = Harness::boot(Accounts::withSecondTenant());
        Scenario::importedEmployer($harness);
        Scenario::importedEmployer($harness, Accounts::SECOND_TENANT, 'employer-2');
        Accounts::importOtherEmployer($harness, Accounts::SECOND_TENANT, 'employer-3');
        $command = $harness->command(self::NAME);

        static::assertSame(0, $command->execute(['--dry-run' => true, '--tenant' => Accounts::SECOND_TENANT, '--limit' => '1']));

        static::assertStringContainsString('Dry run: 1 record(s) would be processed', $command->getDisplay());
    }

    public function testOneMessagePerTenantIsDispatchedWithAsync(): void
    {
        $harness = Harness::boot(self::MESSENGER + Accounts::withSecondTenant(), true);
        Scenario::importedEmployer($harness);
        Scenario::importedEmployer($harness, Accounts::SECOND_TENANT, 'employer-2');
        $command = $harness->command(self::NAME);

        static::assertSame(0, $command->execute(['--async' => true]));

        static::assertStringContainsString('2 message(s) dispatched.', $command->getDisplay());
        $sent = $harness->privateService('messenger.transport.async', InMemoryTransport::class)->getSent();
        $tenants = [];
        foreach ($sent as $envelope) {
            $message = $envelope->getMessage();
            static::assertInstanceOf(SyncEmployerStatusesMessage::class, $message);
            $tenants[] = $message->tenantId;
        }
        sort($tenants);
        static::assertSame(['tenant-1', 'tenant-2'], $tenants);
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_020));
    }

    public function testOnlyTheGivenTenantIsDispatchedWithAsync(): void
    {
        $harness = Harness::boot(self::MESSENGER + Accounts::withSecondTenant(), true);
        Scenario::importedEmployer($harness);
        $command = $harness->command(self::NAME);

        static::assertSame(0, $command->execute(['--async' => true, '--tenant' => Accounts::SECOND_TENANT]));

        static::assertStringContainsString('1 message(s) dispatched.', $command->getDisplay());
    }

    public function testAsyncNeedsMessenger(): void
    {
        $command = Harness::boot()->command(self::NAME);

        static::assertSame(2, $command->execute(['--async' => true]));

        static::assertStringContainsString('--async requires "messenger.enabled".', $command->getDisplay());
    }

    public function testARunningTenantIsSkippedWhileTheOthersAreRefreshed(): void
    {
        $harness = Harness::boot(Accounts::withSecondTenant(), false, true);
        Scenario::importedEmployer($harness);
        $second = Scenario::importedEmployer($harness, Accounts::SECOND_TENANT, 'employer-2');
        $held = HeldLocks::hold($harness, OperationLocks::resource('employers.sync-status', 'tenant-1'));
        $command = $harness->command(self::NAME);

        static::assertSame(0, $command->execute([]));

        static::assertStringContainsString('Already running (lock "urssaf_paje.employers.sync-status.tenant-1" is held): skipped.', $command->getDisplay());
        static::assertStringContainsString($second->accountId, $command->getDisplay());
        static::assertSame(1, $harness->urssaf->calls(HttpScript::ROUTE_020));
        $held->release();
    }

    public function testARunningEmployerRefreshIsSkipped(): void
    {
        $harness = Harness::boot([], false, true);
        $account = Scenario::importedEmployer($harness);
        $held = HeldLocks::hold($harness, OperationLocks::resource('employer.sync-status', $account->accountId));
        $command = $harness->command(self::NAME);

        static::assertSame(0, $command->execute(['--employer' => Scenario::EMPLOYER, '--tenant' => Scenario::TENANT]));

        static::assertStringContainsString(\sprintf('Already running (lock "urssaf_paje.employer.sync-status.%s" is held): skipped.', $account->accountId), $command->getDisplay());
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_020));
        $held->release();
    }
}
