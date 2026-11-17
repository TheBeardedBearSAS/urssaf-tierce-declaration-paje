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

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\OutcomeUnknown;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Child\Child;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\PajeEmployeeNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerReference;
use Throwable;

/**
 * What a live contract test class shares (composition, not inheritance: the project forbids extending test classes):
 * skips without credentials, one shared gateway (one token) and the helpers that record observations without ever
 * echoing URSSAF texts.
 *
 * One accessor per required variable and one translation per failure of the gateway, hence the size and the complexity.
 *
 * @mago-expect lint:too-many-methods
 * @mago-expect lint:cyclomatic-complexity
 */
final class SandboxSession
{
    /** @var list<string> */
    private array $uncatalogued = [];

    private function __construct(
        public readonly SandboxEnvironment $env,
        public readonly SandboxGateway $sandbox,
        private readonly string $testClass,
    ) {}

    /**
     * Skips the calling test unless the credentials (and, for write tests, the opt-in) are set.
     *
     * The flag is the class of tests (read-only or writing), fixed by each test class, not a mode chosen per call.
     *
     * @mago-expect lint:no-boolean-flag-parameter
     * @throws SandboxMisconfigured
     */
    public static function start(string $testClass, bool $writes): self
    {
        $env = SandboxEnvironment::fromProcess();
        $sandbox = SandboxGateway::shared();
        if (!$sandbox instanceof SandboxGateway) {
            TestCase::markTestSkipped(
                'URSSAF sandbox contract tests are opt-in: set URSSAF_SANDBOX_CLIENT_ID and URSSAF_SANDBOX_CLIENT_SECRET (see README, "Contract tests").',
            );
        }
        if ($writes && !$env->writesAllowed()) {
            TestCase::markTestSkipped('Writes real sandbox state: set URSSAF_SANDBOX_ALLOW_WRITES=1 to run (023, 030, 031, 045, 046, 050, 051).');
        }

        return new self($env, $sandbox, $testClass);
    }

    public function requireEmployer(): EmployerReference
    {
        $employer = $this->env->employer();
        if (!$employer instanceof EmployerReference) {
            TestCase::markTestSkipped('Set URSSAF_SANDBOX_EMPLOYER_NUMBER and URSSAF_SANDBOX_EMPLOYER_BIRTH_DATE to run this test.');
        }

        return $employer;
    }

    public function requireEmployee(): PajeEmployeeNumber
    {
        $employee = $this->env->employeeNumber();
        if (!$employee instanceof PajeEmployeeNumber) {
            TestCase::markTestSkipped('Set URSSAF_SANDBOX_EMPLOYEE_NUMBER to run this test.');
        }

        return $employee;
    }

    public function requireChild(): Child
    {
        $child = $this->env->child();
        if (!$child instanceof Child) {
            TestCase::markTestSkipped('Set URSSAF_SANDBOX_CHILD_LAST_NAME, _FIRST_NAME and _BIRTH_DATE to run this test.');
        }

        return $child;
    }

    public function observe(string $point, mixed $value, string $state = SandboxReport::OBSERVED): void
    {
        SandboxReport::record($point, $state, $value, $this->testClass);
    }

    public function observeKey(string $point, string $key, mixed $value): void
    {
        SandboxReport::merge($point, $key, $value, $this->testClass);
    }

    /**
     * Every flow ends here: the test asserts that it did record something about the point it observes.
     */
    public function assertRecorded(string $point): void
    {
        Assert::assertNotSame(SandboxReport::NOT_RUN, SandboxReport::points()[$point]['state'], \sprintf('Nothing was recorded for %s.', $point));
    }

    /**
     * Fails when a call of the test was rejected with a code the ApiErrorCode catalogue does not know. Called once the
     * observations are recorded (tearDown), so that the report keeps them.
     */
    public function assertCodesCatalogued(): void
    {
        SandboxAssert::assertCatalogued($this->uncatalogued);
    }

    /**
     * Runs one gateway call. A business rejection is returned (the caller decides), everything else is translated:
     * authentication failure and unavailability fail with a redacted message, quota (429) and unknown outcome mark the
     * test incomplete (never retried: ADR 0003).
     *
     * @template T
     *
     * @param-immediately-invoked-callable $call
     * @param callable(): T $call
     *
     * @return array{result: null|T, rejected: null|UrssafRequestRejected, exchange: null|Exchange}
     */
    public function attempt(callable $call): array
    {
        $mark = $this->sandbox->http->mark();
        $result = null;
        $rejected = null;

        try {
            $result = $call();
        } catch (UrssafRequestRejected $e) {
            // Collected, not asserted here: the flow goes on and records its observations first (see assertCodesCatalogued).
            $this->uncatalogued = [...$this->uncatalogued, ...SandboxAssert::uncatalogued($e->errors())];
            $rejected = $e;
        } catch (UrssafUnavailable $e) {
            if ($e->isQuotaExceeded()) {
                TestCase::markTestIncomplete('URSSAF quota reached (429), retry later.');
            }
            Assert::fail('Unavailable: ' . SandboxAssert::describe($e));
        } catch (OutcomeUnknown $e) {
            TestCase::markTestIncomplete('Outcome unknown, never retried: ' . SandboxAssert::describe($e));
        } catch (UrssafAuthenticationFailed $e) {
            Assert::fail(SandboxAssert::describe($e));
        } catch (Throwable $e) {
            Assert::fail('Unexpected ' . SandboxAssert::describe($e));
        }

        $apiExchanges = array_values(array_filter(
            $this->sandbox->http->since($mark),
            fn(Exchange $exchange): bool => !$this->sandbox->http->isTokenExchange($exchange),
        ));

        return [
            'result' => $result,
            'rejected' => $rejected,
            'exchange' => [] === $apiExchanges ? null : $apiExchanges[\count($apiExchanges) - 1],
        ];
    }

    /**
     * Status and codes of a call, as recorded in the report: "accepted:200" or "rejected:400:CODE_A,CODE_B".
     *
     * @param array{result: mixed, rejected: null|UrssafRequestRejected, exchange: null|Exchange} $call
     */
    public static function outcome(array $call): string
    {
        $rejected = $call['rejected'];
        if ($rejected instanceof UrssafRequestRejected) {
            return \sprintf('rejected:%d:%s', $rejected->httpStatus(), implode(',', SandboxAssert::codes($rejected->errors())));
        }

        return 'accepted:' . ($call['exchange']->status ?? 0);
    }

    public static function codesOf(?UrssafRequestRejected $rejected): string
    {
        return $rejected instanceof UrssafRequestRejected ? implode(',', SandboxAssert::codes($rejected->errors())) : '';
    }
}
