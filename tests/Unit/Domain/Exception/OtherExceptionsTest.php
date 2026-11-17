<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Exception;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvalidStateTransition;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Validation\ValidationResult;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Validation\Violation;

final class OtherExceptionsTest extends TestCase
{
    public function testInvariantViolation(): void
    {
        $exception = InvariantViolation::because('declaration.deposit_too_high', 'Deposit too high.', ['max' => 50]);

        static::assertSame('declaration.deposit_too_high', $exception->violationCode());
        static::assertSame('Deposit too high.', $exception->getMessage());
        static::assertSame(['max' => 50], $exception->params());
        static::assertSame([], InvariantViolation::because('a.b', 'm')->params());
    }

    public function testInvalidStateTransition(): void
    {
        $exception = InvalidStateTransition::between('declaration_record', 'paid', 'declared');

        static::assertSame('declaration_record.invalid_state_transition', $exception->violationCode());
        static::assertSame('Invalid declaration_record state transition from "paid" to "declared".', $exception->getMessage());
        static::assertSame('declaration_record', $exception->aggregate());
        static::assertSame('paid', $exception->from());
        static::assertSame('declared', $exception->to());
        static::assertSame(['aggregate' => 'declaration_record', 'from' => 'paid', 'to' => 'declared'], $exception->params());
    }

    public function testRecordNotFound(): void
    {
        $exception = RecordNotFound::withIdentifier('employer_account', 'abc-1');

        static::assertSame('employer_account.not_found', $exception->violationCode());
        static::assertSame('No employer_account found for identifier "abc-1".', $exception->getMessage());
        static::assertSame('employer_account', $exception->recordType());
        static::assertSame('abc-1', $exception->identifier());
        static::assertSame(['record_type' => 'employer_account', 'identifier' => 'abc-1'], $exception->params());
    }

    public function testValidationFailedCarriesTheResult(): void
    {
        $result = ValidationResult::of(
            Violation::error('a', 'a.invalid', 'A.'),
            Violation::warning('b', 'b.odd', 'B.'),
            Violation::error('c', 'c.invalid', 'C.'),
        );

        $exception = ValidationFailed::withResult($result);

        static::assertSame($result, $exception->result());
        static::assertSame('validation.failed', $exception->violationCode());
        static::assertSame('Validation failed with 2 error(s).', $exception->getMessage());
        static::assertSame(['error_count' => 2], $exception->params());
    }
}
