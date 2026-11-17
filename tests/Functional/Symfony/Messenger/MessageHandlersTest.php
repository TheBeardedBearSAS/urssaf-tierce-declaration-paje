<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Messenger;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\SyncEmployerStatusesHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\SyncEmployerStatusHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Lock\OperationLocks;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Message\SyncEmployerStatusesMessage;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Message\SyncEmployerStatusMessage;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\MessageHandler\SyncEmployerStatusesMessageHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\MessageHandler\SyncEmployerStatusMessageHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\UrssafException;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Support\RetryDelay;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Fake\EmployerWorld;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Employer\EmployerSamples;

/**
 * The two Messenger handlers over the in-memory fakes of the application layer (no kernel): what they run, under which
 * lock, and how failures reach Messenger. One test per scenario, hence the method count.
 *
 * @mago-expect lint:too-many-methods
 */
final class MessageHandlersTest extends TestCase
{
    private EmployerWorld $world;

    protected function setUp(): void
    {
        $this->world = new EmployerWorld();
    }

    public function testTheTenantHandlerRefreshesTheEmployersOfTheTenantOnly(): void
    {
        $this->world->trackedAccount('account-1', 'ref-1', 'Y0000000000001');
        $this->world->trackedAccount('account-2', 'ref-2', 'Y0000000000002');
        $this->world->trackedAccount('account-3', 'ref-3', 'Y0000000000003', 'tenant-2');
        $this->world->gateway->queue('020', EmployerSamples::verification(), EmployerSamples::verification());

        $this->tenantHandler(new OperationLocks(null))(new SyncEmployerStatusesMessage('tenant-1'));

        static::assertSame(['020', '020'], $this->world->gateway->calls());
        static::assertNotNull($this->lastChecked('account-1'));
        static::assertNotNull($this->lastChecked('account-2'));
        static::assertNull($this->lastChecked('account-3'));
    }

    public function testTheAccountHandlerRefreshesOneEmployer(): void
    {
        $this->world->trackedAccount('account-1', 'ref-1', 'Y0000000000001');
        $this->world->trackedAccount('account-2', 'ref-2', 'Y0000000000002');
        $this->world->gateway->queue('020', EmployerSamples::verification());

        $this->accountHandler(new OperationLocks(null))(new SyncEmployerStatusMessage('tenant-1', 'account-2'));

        static::assertSame(['020'], $this->world->gateway->calls());
        static::assertNull($this->lastChecked('account-1'));
        static::assertNotNull($this->lastChecked('account-2'));
    }

    public function testTheLockIsReleasedAfterTheRun(): void
    {
        $factory = new LockFactory(new InMemoryStore());
        $this->world->trackedAccount();
        $this->world->gateway->queue('020', EmployerSamples::verification(), EmployerSamples::verification());
        $locks = new OperationLocks($factory);

        $this->tenantHandler($locks)(new SyncEmployerStatusesMessage('tenant-1'));
        $this->accountHandler($locks)(new SyncEmployerStatusMessage('tenant-1', 'account-1'));

        static::assertTrue($factory->createLock('urssaf_paje.employers.sync-status.tenant-1')->acquire());
        static::assertTrue($factory->createLock('urssaf_paje.employer.sync-status.account-1')->acquire());
    }

    public function testTheTenantHandlerAsksForARetryWhileTheTenantIsBeingRefreshed(): void
    {
        $factory = new LockFactory(new InMemoryStore());
        $held = $factory->createLock('urssaf_paje.employers.sync-status.tenant-1');
        static::assertTrue($held->acquire());
        $this->world->trackedAccount();

        try {
            $this->tenantHandler(new OperationLocks($factory))(new SyncEmployerStatusesMessage('tenant-1'));
            static::fail('The handler must ask for a retry.');
        } catch (RecoverableMessageHandlingException $retry) {
            RetryDelay::assertCarries(30_000, $retry);
            static::assertStringContainsString('lock "urssaf_paje.employers.sync-status.tenant-1" is held', $retry->getMessage());
        }

        static::assertSame([], $this->world->gateway->calls());
    }

    public function testTheAccountHandlerAsksForARetryWhileTheAccountIsBeingRefreshed(): void
    {
        $factory = new LockFactory(new InMemoryStore());
        $held = $factory->createLock('urssaf_paje.employer.sync-status.account-1');
        static::assertTrue($held->acquire());
        $this->world->trackedAccount();

        try {
            $this->accountHandler(new OperationLocks($factory))(new SyncEmployerStatusMessage('tenant-1', 'account-1'));
            static::fail('The handler must ask for a retry.');
        } catch (RecoverableMessageHandlingException $retry) {
            RetryDelay::assertCarries(30_000, $retry);
            static::assertStringContainsString('lock "urssaf_paje.employer.sync-status.account-1" is held', $retry->getMessage());
        }

        static::assertSame([], $this->world->gateway->calls());
    }

    public function testATemporaryFailureOfUrssafIsRecoverableWithItsDelay(): void
    {
        $this->world->trackedAccount();
        $this->world->gateway->queue('020', UrssafUnavailable::withStatus(Operation::VerifyEmployer, 503, 120, ApiErrors::none()));

        try {
            $this->accountHandler(new OperationLocks(null))(new SyncEmployerStatusMessage('tenant-1', 'account-1'));
            static::fail('Expected a recoverable exception.');
        } catch (RecoverableMessageHandlingException $failure) {
            RetryDelay::assertCarries(120_000, $failure);
        }
    }

    public function testRefusedCredentialsStopTheTenantRunAndAreUnrecoverable(): void
    {
        $this->world->trackedAccount();
        $this->world->gateway->queue('020', UrssafAuthenticationFailed::withStatus(401));

        $this->expectException(UnrecoverableMessageHandlingException::class);

        $this->tenantHandler(new OperationLocks(null))(new SyncEmployerStatusesMessage('tenant-1'));
    }

    public function testADeletedAccountAndAnInvalidIdentifierAreUnrecoverable(): void
    {
        $deleted = fn() => $this->accountHandler(new OperationLocks(null))(new SyncEmployerStatusMessage('tenant-1', 'account-9'));
        $invalid = fn() => $this->tenantHandler(new OperationLocks(null))(new SyncEmployerStatusesMessage(' '));

        foreach ([$deleted, $invalid] as $handle) {
            try {
                $handle();
                static::fail('Expected an unrecoverable exception.');
            } catch (UnrecoverableMessageHandlingException $failure) {
                static::assertInstanceOf(UrssafException::class, $failure->getPrevious());
            }
        }
    }

    private function tenantHandler(OperationLocks $locks): SyncEmployerStatusesMessageHandler
    {
        return new SyncEmployerStatusesMessageHandler(
            new SyncEmployerStatusesHandler($this->world->accounts, $this->world->refresher, new WorkflowPolicy()),
            $locks,
        );
    }

    private function accountHandler(OperationLocks $locks): SyncEmployerStatusMessageHandler
    {
        return new SyncEmployerStatusMessageHandler(
            new SyncEmployerStatusHandler($this->world->locator, $this->world->refresher, $this->world->accounts, $this->world->mandates),
            $locks,
        );
    }

    private function lastChecked(string $accountId): ?string
    {
        return $this->world->accounts->get(EmployerAccountId::fromString($accountId))->lastCheckedAt()?->toIso8601();
    }
}
