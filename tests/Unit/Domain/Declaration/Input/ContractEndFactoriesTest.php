<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Declaration\Input;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\Input\ChildminderContractEndFactory;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\Input\HomeCareContractEndFactory;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Declaration\DeclarationSamples;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\DomainAssertions;

final class ContractEndFactoriesTest extends TestCase
{
    public function testChildminderFromInput(): void
    {
        $end = (new ChildminderContractEndFactory())->fromInput([
            'retirement_indemnity' => '1.00',
            'paid_leave_compensation' => '2.00',
            'notice_indemnity' => '3.00',
            'precarity_indemnity' => '4.00',
            'termination_indemnity' => '5.00',
        ]);

        static::assertSame('1.00', $end->retirementIndemnity()?->toDecimalString());
        static::assertSame('2.00', $end->paidLeaveCompensation()?->toDecimalString());
        static::assertSame('3.00', $end->noticeIndemnity()?->toDecimalString());
        static::assertSame('4.00', $end->precarityIndemnity()?->toDecimalString());
        static::assertSame('5.00', $end->terminationIndemnity()?->toDecimalString());
        static::assertSame([], $end->children());
    }

    public function testHomeCareFromInput(): void
    {
        $end = (new HomeCareContractEndFactory())->fromInput([
            'retirement_indemnity' => '1.00',
            'paid_leave_compensation' => '2.00',
            'notice_indemnity' => '3.00',
            'precarity_indemnity' => '4.00',
            'dismissal_indemnity' => '5.00',
        ]);

        static::assertSame('1.00', $end->retirementIndemnity()?->toDecimalString());
        static::assertSame('2.00', $end->paidLeaveCompensation()?->toDecimalString());
        static::assertSame('3.00', $end->noticeIndemnity()?->toDecimalString());
        static::assertSame('4.00', $end->precarityIndemnity()?->toDecimalString());
        static::assertSame('5.00', $end->dismissalIndemnity()?->toDecimalString());
    }

    public function testEveryAmountIsOptional(): void
    {
        static::assertNull((new ChildminderContractEndFactory())->fromInput([])->noticeIndemnity());
        static::assertNull((new HomeCareContractEndFactory())->fromInput([])->noticeIndemnity());
        static::assertSame([], DomainAssertions::summary((new ChildminderContractEndFactory())->validate([])));
        static::assertSame([], DomainAssertions::summary((new HomeCareContractEndFactory())->validate([])));
    }

    public function testEveryInvalidAmountIsReported(): void
    {
        $invalid = ['retirement_indemnity' => 'x', 'paid_leave_compensation' => '10000', 'notice_indemnity' => '1.234', 'precarity_indemnity' => 1.5];

        static::assertSame(
            [
                'error retirement_indemnity amount.invalid_format',
                'error notice_indemnity amount.too_many_decimals',
                'error precarity_indemnity precarity_indemnity.invalid_type',
                'error termination_indemnity amount.invalid_format',
                'error paid_leave_compensation paid_leave_compensation.too_large',
            ],
            DomainAssertions::summary((new ChildminderContractEndFactory())->validate($invalid + ['termination_indemnity' => 'y'])),
        );
        static::assertSame(
            [
                'error retirement_indemnity amount.invalid_format',
                'error notice_indemnity amount.too_many_decimals',
                'error precarity_indemnity precarity_indemnity.invalid_type',
                'error dismissal_indemnity amount.invalid_format',
                'error paid_leave_compensation paid_leave_compensation.too_large',
            ],
            DomainAssertions::summary((new HomeCareContractEndFactory())->validate($invalid + ['dismissal_indemnity' => 'y'])),
        );
    }

    public function testTheCapIsReportedUnderTheInputKeys(): void
    {
        $tooLarge = ['retirement_indemnity' => '10000', 'paid_leave_compensation' => '10000', 'notice_indemnity' => '10000', 'precarity_indemnity' => '10000'];

        static::assertSame(
            [
                'error retirement_indemnity retirement_indemnity.too_large',
                'error paid_leave_compensation paid_leave_compensation.too_large',
                'error notice_indemnity notice_indemnity.too_large',
                'error precarity_indemnity precarity_indemnity.too_large',
                'error termination_indemnity termination_indemnity.too_large',
            ],
            DomainAssertions::summary((new ChildminderContractEndFactory())->validate($tooLarge + ['termination_indemnity' => '10000'])),
        );
        static::assertSame(
            ['error dismissal_indemnity dismissal_indemnity.too_large'],
            DomainAssertions::summary((new HomeCareContractEndFactory())->validate(['dismissal_indemnity' => '10000'])),
        );
    }

    public function testFromInputThrowsTheViolations(): void
    {
        static::assertSame(
            ['error notice_indemnity notice_indemnity.too_large'],
            DeclarationSamples::violations(static fn(): mixed => (new ChildminderContractEndFactory())->fromInput(['notice_indemnity' => '10000'])),
        );
        static::assertSame(
            ['error notice_indemnity notice_indemnity.too_large'],
            DeclarationSamples::violations(static fn(): mixed => (new HomeCareContractEndFactory())->fromInput(['notice_indemnity' => '10000'])),
        );
    }

    public function testRawInputIsSensitive(): void
    {
        foreach ([ChildminderContractEndFactory::class, HomeCareContractEndFactory::class] as $class) {
            DomainAssertions::assertSensitiveParameters($class, 'validate', ['input']);
            DomainAssertions::assertSensitiveParameters($class, 'fromInput', ['input']);
        }
    }
}
