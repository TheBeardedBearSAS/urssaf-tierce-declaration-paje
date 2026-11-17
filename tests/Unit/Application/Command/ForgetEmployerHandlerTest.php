<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Command;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\ForgetEmployer;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\ForgetEmployerHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Exception\EmployerErasureRefused;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\Mandate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Instant;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Doctrine\Contract\ContractSamples;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Fake\EmployerWorld;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Fake\RacingDeclarationRecords;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Fake\RacingMandates;

/**
 * @mago-expect lint:too-many-methods
 */
final class ForgetEmployerHandlerTest extends TestCase
{
    private EmployerWorld $world;

    protected function setUp(): void
    {
        $this->world = new EmployerWorld();
    }

    public function testErasesTheAccountItsMandatesAndItsRecordsInOneTransaction(): void
    {
        $account = $this->world->trackedAccount();
        $first = $this->world->registeredMandate($account, 'mandate-1');
        $first->cancel(Instant::fromIso8601('2026-11-03T08:00:00Z'));
        $first->releaseEvents();
        $this->world->mandates->save($first);
        $this->world->registeredMandate($account, 'mandate-2');
        $this->world->records->save(ContractSamples::recordOfEmployer(1, 1, DeclarationState::Declared));
        $this->world->records->save(ContractSamples::recordOfEmployer(1, 2, DeclarationState::Pending));
        $this->world->records->save(ContractSamples::recordOfEmployer(2, 3, DeclarationState::Declared));

        $erasure = $this->handler()(new ForgetEmployer(EmployerWorld::tenant(), EmployerWorld::selector(), true, null));

        static::assertSame('account-1', $erasure->accountId);
        static::assertSame(['mandate-1', 'mandate-2'], $erasure->mandateIds);
        static::assertSame(['record-1', 'record-2'], $erasure->declarationRecordIds);
        static::assertSame([], $this->world->accounts->all());
        static::assertSame([], $this->world->mandates->all());
        static::assertCount(1, $this->world->records->all());
        static::assertSame(['EmployerForgotten'], $this->world->publishedEvents());
        static::assertSame(
            ['begin', 'append:urssaf.paje.employer.forgotten', 'commit', 'publish:EmployerForgotten'],
            $this->world->journal->entries(),
        );
        static::assertSame([], $this->world->gateway->calls());
    }

    public function testRefusesAnActiveMandateByDefault(): void
    {
        $account = $this->world->trackedAccount();
        $this->world->registeredMandate($account);

        $this->assertRefused('employer_account.forget_active_mandate', new ForgetEmployer(EmployerWorld::tenant(), EmployerWorld::selector(), null, null));
    }

    public function testAnExplicitFalseRefusesAnActiveMandateWhateverThePolicy(): void
    {
        $account = $this->world->trackedAccount();
        $this->world->registeredMandate($account);

        $this->assertRefused(
            'employer_account.forget_active_mandate',
            new ForgetEmployer(EmployerWorld::tenant(), EmployerWorld::selector(), false, null),
            (new WorkflowPolicy())->withAllowErasureWithActiveMandate(true),
        );
    }

    public function testThePolicyMayAllowAnActiveMandate(): void
    {
        $account = $this->world->trackedAccount();
        $this->world->registeredMandate($account);

        $erasure = $this->handler((new WorkflowPolicy())->withAllowErasureWithActiveMandate(true))(
            new ForgetEmployer(EmployerWorld::tenant(), EmployerWorld::selector(), null, null),
        );

        static::assertSame(['mandate-1'], $erasure->mandateIds);
    }

    public function testACancelledMandateIsNotAnObstacle(): void
    {
        $account = $this->world->trackedAccount();
        $mandate = $this->world->registeredMandate($account);
        $mandate->cancel(Instant::fromIso8601('2026-11-03T08:00:00Z'));
        $mandate->releaseEvents();
        $this->world->mandates->save($mandate);

        $erasure = $this->handler()(new ForgetEmployer(EmployerWorld::tenant(), EmployerWorld::selector(), null, null));

        static::assertSame(['mandate-1'], $erasure->mandateIds);
    }

    public function testAlwaysRefusesASubmissionInFlight(): void
    {
        $this->world->trackedAccount();
        $this->world->records->save(ContractSamples::recordOfEmployer(1, 1, DeclarationState::Submitting));

        $this->assertRefused(
            'employer_account.forget_submission_in_flight',
            new ForgetEmployer(EmployerWorld::tenant(), EmployerWorld::selector(), true, true),
            (new WorkflowPolicy())->withAllowErasureWithUnknownOutcomes(true),
        );
    }

    public function testRefusesAnUnknownOutcomeByDefaultWithItsCount(): void
    {
        $this->world->trackedAccount();
        $this->world->records->save(ContractSamples::recordOfEmployer(1, 1, DeclarationState::OutcomeUnknown));
        $this->world->records->save(ContractSamples::recordOfEmployer(1, 2, DeclarationState::OutcomeUnknown));

        try {
            $this->handler()(new ForgetEmployer(EmployerWorld::tenant(), EmployerWorld::selector(), null, null));
            static::fail('Expected EmployerErasureRefused.');
        } catch (EmployerErasureRefused $refused) {
            static::assertSame('employer_account.forget_unknown_outcome', $refused->violationCode());
            static::assertSame(['employer_account_id' => 'account-1', 'count' => 2], $refused->params());
        }

        static::assertCount(1, $this->world->accounts->all());
        static::assertCount(2, $this->world->records->all());
    }

    public function testAnUnknownOutcomeMayBeAllowedByTheCommandOrThePolicy(): void
    {
        $this->world->trackedAccount();
        $this->world->records->save(ContractSamples::recordOfEmployer(1, 1, DeclarationState::OutcomeUnknown));

        $viaCommand = $this->handler()(new ForgetEmployer(EmployerWorld::tenant(), EmployerWorld::selector(), null, true));

        static::assertSame(['record-1'], $viaCommand->declarationRecordIds);

        $this->world->trackedAccount();
        $this->world->records->save(ContractSamples::recordOfEmployer(1, 1, DeclarationState::OutcomeUnknown));

        $viaPolicy = $this->handler((new WorkflowPolicy())->withAllowErasureWithUnknownOutcomes(true))(
            new ForgetEmployer(EmployerWorld::tenant(), EmployerWorld::selector(), null, null),
        );

        static::assertSame(['record-1'], $viaPolicy->declarationRecordIds);
    }

    public function testNeverErasesASubmissionAndErasesAnUnknownOutcomeOnlyWhenAllowed(): void
    {
        $this->world->trackedAccount();
        $records = new RacingDeclarationRecords($this->world->records, ContractSamples::recordOfEmployer(2, 8, DeclarationState::Pending));

        $this->handlerWith($this->world->mandates, $records)(new ForgetEmployer(EmployerWorld::tenant(), EmployerWorld::selector(), null, false));

        static::assertSame(
            [
                DeclarationState::Pending,
                DeclarationState::Declared,
                DeclarationState::Rejected,
                DeclarationState::AlreadyDeclared,
                DeclarationState::Error,
                DeclarationState::InvalidCredentials,
            ],
            $records->requestedStates(),
        );

        $this->world->trackedAccount();
        $this->handlerWith($this->world->mandates, $records)(new ForgetEmployer(EmployerWorld::tenant(), EmployerWorld::selector(), null, true));

        static::assertSame(
            [
                DeclarationState::Pending,
                DeclarationState::Declared,
                DeclarationState::Rejected,
                DeclarationState::AlreadyDeclared,
                DeclarationState::OutcomeUnknown,
                DeclarationState::Error,
                DeclarationState::InvalidCredentials,
            ],
            $records->requestedStates(),
        );
    }

    public function testChecksTheRefusalsInOrder(): void
    {
        $account = $this->world->trackedAccount();
        $this->world->registeredMandate($account);
        $this->world->records->save(ContractSamples::recordOfEmployer(1, 1, DeclarationState::Submitting));
        $this->world->records->save(ContractSamples::recordOfEmployer(1, 2, DeclarationState::OutcomeUnknown));

        $this->assertRefused('employer_account.forget_active_mandate', new ForgetEmployer(EmployerWorld::tenant(), EmployerWorld::selector(), null, null));
        $this->assertRefused('employer_account.forget_submission_in_flight', new ForgetEmployer(EmployerWorld::tenant(), EmployerWorld::selector(), true, null));
        $this->world->records->delete(DeclarationRecordId::fromString('record-1'));
        $this->assertRefused('employer_account.forget_unknown_outcome', new ForgetEmployer(EmployerWorld::tenant(), EmployerWorld::selector(), true, null));
    }

    public function testRefusesAnUnknownOrForeignAccount(): void
    {
        $this->world->trackedAccount();

        $this->expectException(RecordNotFound::class);

        $this->handler()(new ForgetEmployer(EmployerWorld::tenant('tenant-2'), EmployerWorld::selector(), null, null));
    }

    public function testRollsBackWhenARecordAppearsDuringTheErasure(): void
    {
        $this->world->trackedAccount();
        $records = new RacingDeclarationRecords(
            $this->world->records,
            ContractSamples::recordOfEmployer(1, 9, DeclarationState::Pending),
        );

        $this->assertConcurrent($this->handlerWith($this->world->mandates, $records));
    }

    public function testRollsBackWhenAMandateAppearsDuringTheErasure(): void
    {
        $account = $this->world->trackedAccount();
        $this->world->registeredMandate($account);
        $mandates = new RacingMandates(
            $this->world->mandates,
            Mandate::register(
                MandateId::fromString('mandate-9'),
                EmployerWorld::tenant(),
                EmployerWorld::reference(),
                EmployerWorld::employer(),
                EmployerAccountId::fromString('account-1'),
                Instant::fromIso8601('2026-11-17T08:00:00Z'),
            ),
        );

        $this->assertConcurrent($this->handlerWith($mandates, $this->world->records), true);
    }

    private function assertConcurrent(ForgetEmployerHandler $handler, bool $allowActiveMandate = false): void
    {
        try {
            $handler(new ForgetEmployer(EmployerWorld::tenant(), EmployerWorld::selector(), $allowActiveMandate, null));
            static::fail('Expected InvariantViolation.');
        } catch (InvariantViolation $violation) {
            static::assertSame('employer_account.concurrent_modification', $violation->violationCode());
            static::assertSame(['id' => 'account-1'], $violation->params());
        }

        static::assertSame(['begin', 'rollback'], $this->world->journal->entries());
        static::assertSame([], $this->world->publishedEvents());
        // Rolled back: the account is still there.
        static::assertCount(1, $this->world->accounts->all());
    }

    private function assertRefused(string $code, ForgetEmployer $command, WorkflowPolicy $policy = new WorkflowPolicy()): void
    {
        try {
            $this->handler($policy)($command);
            static::fail('Expected EmployerErasureRefused.');
        } catch (EmployerErasureRefused $refused) {
            static::assertSame($code, $refused->violationCode());
        }

        static::assertCount(1, $this->world->accounts->all());
        static::assertSame([], $this->world->publishedEvents());
    }

    private function handler(WorkflowPolicy $policy = new WorkflowPolicy()): ForgetEmployerHandler
    {
        return $this->handlerWith($this->world->mandates, $this->world->records, $policy);
    }

    private function handlerWith(MandateRepository $mandates, DeclarationRecordRepository $records, WorkflowPolicy $policy = new WorkflowPolicy()): ForgetEmployerHandler
    {
        return new ForgetEmployerHandler(
            $this->world->locator,
            $this->world->accounts,
            $mandates,
            $records,
            $this->world->clock,
            $this->world->unitOfWork,
            $policy,
        );
    }
}
