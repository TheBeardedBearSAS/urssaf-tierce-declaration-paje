<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Contract;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Child\Child;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationResult;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\PreDeclarationResult;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeAssociation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeCreation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeIdentification;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\PajeEmployeeNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerVerification;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Exception\ApiErrorCode;
use Throwable;

/**
 * Operations that write real sandbox state, run only with URSSAF_SANDBOX_ALLOW_WRITES=1: mandate registration and
 * cancellation (030, 031), association and creation of fictitious employees (023), pre-declarations (045, 046) and the
 * declarations themselves (050, 051, irreversible: sent once, never resent).
 *
 * The tests run in a random order: each one is self-contained and records the state it finds instead of assuming it.
 * Business refusals (a closed declaration window, a duplicate, a missing mandate) are observations, not failures.
 *
 * One method per operation and per observed point, hence the method count and the complexity.
 *
 * @mago-expect lint:too-many-methods
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
#[Group('contract')]
#[Group('contract-writes')]
final class WriteSandboxContractTest extends TestCase
{
    private SandboxSession $session;

    private ?SandboxSession $started = null;

    public static function tearDownAfterClass(): void
    {
        SandboxReport::flush();
    }

    protected function tearDown(): void
    {
        // Null when the test was skipped: no session was built.
        $this->started?->assertCodesCatalogued();
    }

    protected function setUp(): void
    {
        $this->started = SandboxSession::start(self::class, true);
        $this->session = $this->started;
    }

    /**
     * 030 and 031 in one method: the mandate state is shared by the whole sandbox employer, so one test owns the whole
     * cycle (register, register again, cancel, cancel again, probe without mandate) and always registers again at the end.
     */
    public function testMandateCycleSettlesThePrerequisiteAndTheRetrySafety(): void
    {
        $employer = $this->session->requireEmployer();
        $employee = $this->session->env->employeeNumber();
        $child = $this->session->env->child();

        $first = $this->registerMandate($employer);
        $this->session->observeKey('mandate-prerequisite', '030-first', SandboxSession::outcome($first));
        if (true !== $first['result']) {
            // Held by another platform, or refused: nothing is cancelled that this run did not register.
            static::markTestIncomplete('The mandate could not be registered: ' . SandboxSession::outcome($first));
        }

        try {
            $second = $this->registerMandate($employer);
            $this->session->observe('retry-safety-030', ['first' => SandboxSession::outcome($first), 'second' => SandboxSession::outcome($second)]);

            $cancelled = $this->cancelMandate($employer);
            $cancelledAgain = $this->cancelMandate($employer);
            $this->session->observe('retry-safety-031', ['first' => SandboxSession::outcome($cancelled), 'second' => SandboxSession::outcome($cancelledAgain)]);

            $this->probeWithoutMandate($employer, $employee, $child);
        } finally {
            $restored = $this->restoreMandate($employer);
        }

        static::assertTrue($restored, 'The mandate was not registered again at the end of the cycle: the sandbox employer lost it.');
        $this->session->assertRecorded('mandate-prerequisite');
        $this->session->assertRecorded('retry-safety-030');
        $this->session->assertRecorded('retry-safety-031');
    }

    /**
     * 023 with the employee known by its number: a second identical call shows whether the operation is safe to retry.
     * Then which fields of a creation the platform really requires (PDF: mandatory; Swagger: optional), with a new
     * fictitious identity per variant.
     */
    public function testAssociationSettlesTheRetrySafetyAndTheRequiredFields(): void
    {
        $employer = $this->session->requireEmployer();
        $employee = $this->session->requireEmployee();

        $first = $this->associate($employer, SandboxData::byNumber($employee), SandboxData::emptyCreation());
        $second = $this->associate($employer, SandboxData::byNumber($employee), SandboxData::emptyCreation());
        $this->session->observe('retry-safety-023', ['first' => $this->associationOutcome($first), 'second' => $this->associationOutcome($second)]);

        $variants = [
            'number-empty-body' => $first,
        ];
        $identity = SandboxData::newIdentity();
        $variants['identity-only'] = $this->associate($employer, SandboxData::byIdentity($identity), SandboxData::identityOnlyCreation($identity));
        $creations = [
            'complete' => SandboxData::completeCreation(...),
            'without-address' => SandboxData::creationWithoutAddress(...),
            'without-activity' => SandboxData::creationWithoutActivity(...),
        ];
        foreach ($creations as $name => $creation) {
            $identity = SandboxData::newIdentity();
            $variants['creation-' . $name] = $this->associate($employer, SandboxData::byIdentity($identity), $creation($identity));
        }

        $outcomes = [];
        foreach ($variants as $name => $call) {
            $outcomes[$name] = $this->associationOutcome($call);
        }
        $this->session->observe('023-required-fields', $outcomes);
        $this->session->assertRecorded('023-required-fields');
    }

    public function testPreDeclaresAChildminderAndAHomeCareSalary(): void
    {
        $employer = $this->session->requireEmployer();
        $employee = $this->session->requireEmployee();
        $child = $this->session->requireChild();
        $today = SandboxData::today();

        $childminder = $this->preDeclareChildminder($employer, $employee, $child, $today);
        $homeCare = $this->preDeclareHomeCare($employer, $employee, $child, $today);

        $this->observePreDeclaration('045', $childminder);
        $this->observePreDeclaration('046', $homeCare);
        foreach ([$childminder, $homeCare] as $call) {
            if (!$call['result'] instanceof PreDeclarationResult) {
                continue;
            }

            $exchange = $call['exchange'];
            static::assertInstanceOf(Exchange::class, $exchange);
            static::assertSame(200, $exchange->status);
            static::assertContains('declaration', $exchange->bodyKeys, 'The answer carries no declaration amounts.');
        }
        $this->session->assertRecorded('predeclaration');
    }

    /**
     * 050 and 051 record a declaration for good: each one is sent at most once, only after its pre-declaration was
     * accepted, and a refusal (closed window, duplicate of an earlier run) is only observed.
     */
    public function testDeclaresAChildminderAndAHomeCareSalaryOnce(): void
    {
        $employer = $this->session->requireEmployer();
        $employee = $this->session->requireEmployee();
        $child = $this->session->requireChild();
        $today = SandboxData::today();

        $this->declareOnceAfter(
            '050',
            $this->preDeclareChildminder($employer, $employee, $child, $today),
            fn(): DeclarationResult => $this->session->sandbox->gateway->declareChildminder(
                $this->session->sandbox->tenantId,
                $employer,
                SandboxData::childminderDeclaration($employee, $child, $today),
            ),
        );
        $this->declareOnceAfter(
            '051',
            $this->preDeclareHomeCare($employer, $employee, $child, $today),
            fn(): DeclarationResult => $this->session->sandbox->gateway->declareHomeCare(
                $this->session->sandbox->tenantId,
                $employer,
                SandboxData::homeCareDeclaration($employee, $child, $today),
            ),
        );

        $this->session->assertRecorded('declaration');
    }

    /**
     * Sends the declaration once when its pre-declaration was accepted, never resends it, and records what happened.
     *
     * @param-immediately-invoked-callable $declare
     * @param array{result: null|PreDeclarationResult, rejected: null|UrssafRequestRejected, exchange: null|Exchange} $pre
     * @param callable(): DeclarationResult                                                                           $declare
     */
    private function declareOnceAfter(string $operation, array $pre, callable $declare): void
    {
        if (!$pre['result'] instanceof PreDeclarationResult) {
            $this->session->observeKey('declaration', $operation, 'not sent, pre-declaration ' . SandboxSession::outcome($pre));

            return;
        }

        $this->observeDeclaration($operation, $this->session->attempt($declare));
    }

    /**
     * Registers the mandate again, whatever happened: the sandbox employer must keep its mandate for the other tests.
     * The outcome goes to the report; the caller fails on a refusal once the test body is done.
     */
    private function restoreMandate(EmployerReference $employer): bool
    {
        try {
            $call = $this->registerMandate($employer);
        } catch (Throwable) {
            // Best effort: whatever failed here is reported as a failed restoration, never as the error of the test.
            $this->session->observeKey('mandate-prerequisite', 'restored', 'failed');

            return false;
        }

        $this->session->observeKey('mandate-prerequisite', 'restored', SandboxSession::outcome($call));

        return true === $call['result'];
    }

    /**
     * Each employer-scoped operation, without any mandate, to see which ones answer MANDAT_INEXISTANT (the PDF does not
     * say, api-reference section 5). 050 and 051 are not probed: they are irreversible.
     */
    private function probeWithoutMandate(EmployerReference $employer, ?PajeEmployeeNumber $employee, ?Child $child): void
    {
        /** @var array<string, array{result: mixed, rejected: null|UrssafRequestRejected, exchange: null|Exchange}> $calls */
        $calls = [
            Operation::VerifyEmployer->value => $this->session->attempt(fn(): EmployerVerification => $this->session->sandbox->gateway->verifyEmployer(
                $this->session->sandbox->tenantId,
                $employer,
            )),
        ];
        if ($child instanceof Child) {
            $calls[Operation::VerifyChildren->value] = $this->session->attempt(fn(): array => $this->session->sandbox->gateway->verifyChildren(
                $this->session->sandbox->tenantId,
                $employer,
                [$child],
            ));
        }
        if ($employee instanceof PajeEmployeeNumber) {
            $calls[Operation::AssociateEmployee->value] = $this->associate($employer, SandboxData::byNumber($employee), SandboxData::emptyCreation());
        }
        if ($employee instanceof PajeEmployeeNumber && $child instanceof Child) {
            $today = SandboxData::today();
            $calls[Operation::PreDeclareChildminder->value] = $this->preDeclareChildminder($employer, $employee, $child, $today);
            $calls[Operation::PreDeclareHomeCare->value] = $this->preDeclareHomeCare($employer, $employee, $child, $today);
        }

        $outcomes = [];
        $missing = [];
        foreach ($calls as $operation => $call) {
            $outcomes[$operation] = SandboxSession::outcome($call);
            if ($call['rejected']?->hasErrorCode(ApiErrorCode::MandateMissing->value) ?? false) {
                $missing[] = $operation;
            }
        }

        $this->session->observeKey('mandate-prerequisite', 'withoutMandate', $outcomes);
        $this->session->observeKey('mandate-prerequisite', 'answeringMandateMissing', $missing);
    }

    /**
     * @return array{result: null|bool, rejected: null|UrssafRequestRejected, exchange: null|Exchange}
     */
    private function registerMandate(EmployerReference $employer): array
    {
        return $this->session->attempt(function () use ($employer): bool {
            $this->session->sandbox->gateway->registerMandate($this->session->sandbox->tenantId, $employer);

            return true;
        });
    }

    /**
     * @return array{result: null|bool, rejected: null|UrssafRequestRejected, exchange: null|Exchange}
     */
    private function cancelMandate(EmployerReference $employer): array
    {
        return $this->session->attempt(function () use ($employer): bool {
            $this->session->sandbox->gateway->cancelMandate($this->session->sandbox->tenantId, $employer);

            return true;
        });
    }

    /**
     * @return array{result: null|EmployeeAssociation, rejected: null|UrssafRequestRejected, exchange: null|Exchange}
     */
    private function associate(EmployerReference $employer, EmployeeIdentification $employee, EmployeeCreation $creation): array
    {
        return $this->session->attempt(fn(): EmployeeAssociation => $this->session->sandbox->gateway->associateEmployee(
            $this->session->sandbox->tenantId,
            $employer,
            $employee,
            $creation,
        ));
    }

    /**
     * @return array{result: null|PreDeclarationResult, rejected: null|UrssafRequestRejected, exchange: null|Exchange}
     */
    private function preDeclareChildminder(EmployerReference $employer, PajeEmployeeNumber $employee, Child $child, DateTimeImmutable $today): array
    {
        return $this->session->attempt(fn(): PreDeclarationResult => $this->session->sandbox->gateway->preDeclareChildminder(
            $this->session->sandbox->tenantId,
            $employer,
            SandboxData::childminderDeclaration($employee, $child, $today),
        ));
    }

    /**
     * @return array{result: null|PreDeclarationResult, rejected: null|UrssafRequestRejected, exchange: null|Exchange}
     */
    private function preDeclareHomeCare(EmployerReference $employer, PajeEmployeeNumber $employee, Child $child, DateTimeImmutable $today): array
    {
        return $this->session->attempt(fn(): PreDeclarationResult => $this->session->sandbox->gateway->preDeclareHomeCare(
            $this->session->sandbox->tenantId,
            $employer,
            SandboxData::homeCareDeclaration($employee, $child, $today),
        ));
    }

    /**
     * @param array{result: null|EmployeeAssociation, rejected: null|UrssafRequestRejected, exchange: null|Exchange} $call
     */
    private function associationOutcome(array $call): string
    {
        $result = $call['result'];
        if (!$result instanceof EmployeeAssociation) {
            return SandboxSession::outcome($call);
        }

        return \sprintf(
            '%s:created=%d:associated=%d:already=%d:nonBlocking=%s',
            SandboxSession::outcome($call),
            (int) $result->created(),
            (int) $result->associated(),
            (int) $result->alreadyAssociated(),
            implode(',', $call['exchange']->nonBlockingCodes ?? []),
        );
    }

    /**
     * @param array{result: null|PreDeclarationResult, rejected: null|UrssafRequestRejected, exchange: null|Exchange} $call
     */
    private function observePreDeclaration(string $operation, array $call): void
    {
        $this->session->observeKey('predeclaration', $operation, [
            'outcome' => SandboxSession::outcome($call),
            'nonBlocking' => $call['exchange']->nonBlockingCodes ?? [],
            'amountsPresent' => $call['result']?->amounts() !== null,
        ]);
    }

    /**
     * @param array{result: null|DeclarationResult, rejected: null|UrssafRequestRejected, exchange: null|Exchange} $call
     */
    private function observeDeclaration(string $operation, array $call): void
    {
        $this->session->observeKey('declaration', $operation, [
            'outcome' => SandboxSession::outcome($call),
            'nonBlocking' => $call['exchange']->nonBlockingCodes ?? [],
            'referenceType' => $call['exchange']->jsonTypes['referenceDocumentaire'] ?? 'absent',
            'referencePresent' => null !== $call['result']?->documentReference(),
        ]);
    }
}
