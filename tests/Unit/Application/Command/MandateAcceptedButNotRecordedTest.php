<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Command;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\CancelMandate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\CancelMandateHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\ImportMandate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\ImportMandateHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\RecordMandateCancellation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\RecordMandateCancellationHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\RegisterMandate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\RegisterMandateHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Exception\MandateRecordingFailed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service\IgnoredFailures;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service\MandateRecorder;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service\UnitOfWork;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\PajeEmployerNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\Mandate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Transaction\ImmediateTransactionManager;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Fake\ConcurrentAccounts;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Fake\ConcurrentMandates;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Fake\EmployerWorld;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Fake\FailingOutbox;
use Throwable;

/**
 * Only MandateRecordingFailed means that 030 / 031 was accepted (ADR 0006, settlement table): the 12 combinations of
 * register / cancel, a failing account save, mandate save or outbox, and a rolling-back or an immediate transaction manager.
 * After the failure the test checks what is stored, runs the call-free recovery (ImportMandate / RecordMandateCancellation)
 * and checks that URSSAF was called once and that the aggregates end consistent.
 *
 * Every other failure of the call is "not sent" or "unknown": nothing is recorded.
 *
 * Table-driven: one data set per combination, hence the complexity.
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class MandateAcceptedButNotRecordedTest extends TestCase
{
    private const ROLLING_BACK = 'rolling back';

    private const IMMEDIATE = 'immediate';

    /**
     * [operation, failing store, manager, expected]: the mandates stored (state) and the account stage right after the
     * failure, and the origins of the mandates once the call-free recovery ran.
     *
     * @return iterable<string, array{string, string, string, array{mandates: list<string>, stage: string, origins: list<string>}}>
     */
    public static function failures(): iterable
    {
        // Registration: the mandate is saved first, then the account, then the outbox.
        yield 'register, account failure, rolling back' => ['register', 'account', self::ROLLING_BACK, ['mandates' => [], 'stage' => 'tracked', 'origins' => ['imported']]];
        yield 'register, account failure, immediate' => ['register', 'account', self::IMMEDIATE, ['mandates' => ['registered'], 'stage' => 'tracked', 'origins' => ['registered']]];
        yield 'register, mandate failure, rolling back' => ['register', 'mandate', self::ROLLING_BACK, ['mandates' => [], 'stage' => 'tracked', 'origins' => ['imported']]];
        yield 'register, mandate failure, immediate' => ['register', 'mandate', self::IMMEDIATE, ['mandates' => [], 'stage' => 'tracked', 'origins' => ['imported']]];
        yield 'register, outbox failure, rolling back' => ['register', 'outbox', self::ROLLING_BACK, ['mandates' => [], 'stage' => 'tracked', 'origins' => ['imported']]];
        yield 'register, outbox failure, immediate' => ['register', 'outbox', self::IMMEDIATE, ['mandates' => ['registered'], 'stage' => 'mandate_registered', 'origins' => ['registered']]];
        // Cancellation: the mandate is saved first, then the account, then the outbox.
        yield 'cancel, account failure, rolling back' => ['cancel', 'account', self::ROLLING_BACK, ['mandates' => ['registered'], 'stage' => 'mandate_registered', 'origins' => ['registered']]];
        yield 'cancel, account failure, immediate' => ['cancel', 'account', self::IMMEDIATE, ['mandates' => ['cancelled'], 'stage' => 'mandate_registered', 'origins' => ['registered']]];
        yield 'cancel, mandate failure, rolling back' => ['cancel', 'mandate', self::ROLLING_BACK, ['mandates' => ['registered'], 'stage' => 'mandate_registered', 'origins' => ['registered']]];
        yield 'cancel, mandate failure, immediate' => ['cancel', 'mandate', self::IMMEDIATE, ['mandates' => ['registered'], 'stage' => 'mandate_registered', 'origins' => ['registered']]];
        yield 'cancel, outbox failure, rolling back' => ['cancel', 'outbox', self::ROLLING_BACK, ['mandates' => ['registered'], 'stage' => 'mandate_registered', 'origins' => ['registered']]];
        yield 'cancel, outbox failure, immediate' => ['cancel', 'outbox', self::IMMEDIATE, ['mandates' => ['cancelled'], 'stage' => 'mandate_cancelled', 'origins' => ['registered']]];
    }

    /**
     * @param array{mandates: list<string>, stage: string, origins: list<string>} $expected
     */
    #[DataProvider('failures')]
    public function testAnAcceptedCallThatCannotBeRecordedIsRecoveredWithoutAnyCall(
        string $operation,
        string $store,
        string $manager,
        array $expected,
    ): void {
        $register = $operation === 'register';
        $world = new EmployerWorld($manager === self::ROLLING_BACK);
        $account = $world->trackedAccount();
        if (!$register) {
            $world->registeredMandate($account);
        }
        $world->gateway->queue($register ? '030' : '031', null);
        $storage = new RuntimeException('storage down');

        $failure = $this->recordingFailure($world, $operation, $store, $storage);

        static::assertSame('mandate.recording_failed', $failure->violationCode());
        static::assertSame(['operation' => $register ? '030' : '031', 'attempts' => 1, 'last_code' => RuntimeException::class], $failure->params());
        static::assertSame($storage, $failure->getPrevious());
        static::assertSame($expected['mandates'], $this->mandateStates($world));
        static::assertSame($expected['stage'], $world->accounts->get(EmployerAccountId::fromString('account-1'))->stage()->value);
        static::assertSame($register ? ['030'] : ['031'], $world->gateway->calls());

        // Recovery: no call, and both aggregates end consistent.
        $recovered = $register
            ? (new ImportMandateHandler($world->locator, $world->mandates, $world->recorder))(new ImportMandate(EmployerWorld::tenant(), EmployerWorld::selector()))
            : (new RecordMandateCancellationHandler($world->locator, $world->mandates, $world->recorder))(new RecordMandateCancellation(EmployerWorld::tenant(), EmployerWorld::selector()));

        static::assertSame($register ? 'registered' : 'cancelled', $recovered->state);
        static::assertSame($register ? ['030'] : ['031'], $world->gateway->calls());
        static::assertSame($register ? ['registered'] : ['cancelled'], $this->mandateStates($world));
        static::assertSame($expected['origins'], $this->mandateOrigins($world));
        static::assertSame(
            $register ? 'mandate_registered' : 'mandate_cancelled',
            $world->accounts->get(EmployerAccountId::fromString('account-1'))->stage()->value,
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function operations(): iterable
    {
        yield 'register' => ['register'];
        yield 'cancel' => ['cancel'];
    }

    #[DataProvider('operations')]
    public function testMissingCredentialsMeanNotSentAndRecordNothing(string $operation): void
    {
        $register = $operation === 'register';
        $world = new EmployerWorld();
        $account = $world->trackedAccount();
        if (!$register) {
            $world->registeredMandate($account);
        }
        $world->gateway->queue($register ? '030' : '031', RecordNotFound::withIdentifier('credentials', 'tenant-1'));

        $failure = $this->failureOf($world, $operation);

        static::assertInstanceOf(RecordNotFound::class, $failure);
        static::assertSame('credentials.not_found', $failure->violationCode());
        static::assertSame($register ? [] : ['registered'], $this->mandateStates($world));
        static::assertSame($register ? 'tracked' : 'mandate_registered', $world->accounts->get(EmployerAccountId::fromString('account-1'))->stage()->value);
        static::assertSame([], $world->publisher->names());
    }

    #[DataProvider('operations')]
    public function testAnUnexpectedStatusIsNotReportedAsAccepted(string $operation): void
    {
        $register = $operation === 'register';
        $world = new EmployerWorld();
        $account = $world->trackedAccount();
        if (!$register) {
            $world->registeredMandate($account);
        }
        $world->gateway->queue($register ? '030' : '031', UnexpectedUrssafResponse::unexpectedStatus(404));

        $failure = $this->failureOf($world, $operation);

        static::assertInstanceOf(UnexpectedUrssafResponse::class, $failure);
        static::assertSame($register ? [] : ['registered'], $this->mandateStates($world));
        static::assertSame($register ? 'tracked' : 'mandate_registered', $world->accounts->get(EmployerAccountId::fromString('account-1'))->stage()->value);
        static::assertSame([], $world->publisher->names());
    }

    public function testTheCompletionOfAnAccountWithoutCallIsNeverReportedAsAnAcceptedRegistration(): void
    {
        $world = new EmployerWorld(false);
        $account = $world->trackedAccount();
        $world->leftBehindMandate($account);
        $storage = new RuntimeException('storage down');
        $accounts = new ConcurrentAccounts($world->accounts, null);
        $accounts->beforeSave(static fn(): never => throw $storage);
        $recorder = new MandateRecorder($world->mandates, $accounts, $world->ids, $world->clock, $world->unitOfWork);

        try {
            (new RegisterMandateHandler($world->locator, $world->mandates, $world->gateway, $recorder))(new RegisterMandate(EmployerWorld::tenant(), EmployerWorld::selector()));
            static::fail('Expected the storage failure.');
        } catch (Throwable $failure) {
            static::assertSame($storage, $failure);
        }

        static::assertSame([], $world->gateway->calls());
        static::assertSame('tracked', $world->accounts->get(EmployerAccountId::fromString('account-1'))->stage()->value);
    }

    private function recordingFailure(EmployerWorld $world, string $operation, string $store, Throwable $storage): MandateRecordingFailed
    {
        $register = $operation === 'register';
        $accounts = new ConcurrentAccounts($world->accounts, null);
        $mandates = new ConcurrentMandates($world->mandates, null);
        $unitOfWork = $world->unitOfWork;
        match ($store) {
            'account' => $accounts->beforeSave(static fn(): never => throw $storage),
            'mandate' => $mandates->beforeSave(static fn(): never => throw $storage),
            default => $unitOfWork = new UnitOfWork($world->transactions ?? new ImmediateTransactionManager(), $world->publisher, new IgnoredFailures(), new FailingOutbox($storage)),
        };
        $recorder = new MandateRecorder($mandates, $accounts, $world->ids, $world->clock, $unitOfWork);

        try {
            $register
                ? (new RegisterMandateHandler($world->locator, $world->mandates, $world->gateway, $recorder))(new RegisterMandate(EmployerWorld::tenant(), EmployerWorld::selector()))
                : (new CancelMandateHandler($world->locator, $world->mandates, $world->gateway, $recorder))(new CancelMandate(EmployerWorld::tenant(), EmployerWorld::selector()));
        } catch (MandateRecordingFailed $failure) {
            return $failure;
        }
        static::fail('Expected MandateRecordingFailed.');
    }

    private function failureOf(EmployerWorld $world, string $operation): Throwable
    {
        $register = $operation === 'register';
        try {
            $register
                ? (new RegisterMandateHandler($world->locator, $world->mandates, $world->gateway, $world->recorder))(new RegisterMandate(EmployerWorld::tenant(), EmployerWorld::selector()))
                : (new CancelMandateHandler($world->locator, $world->mandates, $world->gateway, $world->recorder))(new CancelMandate(EmployerWorld::tenant(), EmployerWorld::selector()));
        } catch (Throwable $failure) {
            return $failure;
        }
        static::fail('Expected a failure.');
    }

    /**
     * @return list<string>
     */
    private function mandateStates(EmployerWorld $world): array
    {
        return array_map(
            static fn(Mandate $mandate): string => $mandate->state()->value,
            $world->mandates->findByEmployer(EmployerWorld::tenant(), PajeEmployerNumber::of(EmployerWorld::NUMBER)),
        );
    }

    /**
     * @return list<string>
     */
    private function mandateOrigins(EmployerWorld $world): array
    {
        return array_map(
            static fn(Mandate $mandate): string => $mandate->origin()->value,
            $world->mandates->findByEmployer(EmployerWorld::tenant(), PajeEmployerNumber::of(EmployerWorld::NUMBER)),
        );
    }
}
