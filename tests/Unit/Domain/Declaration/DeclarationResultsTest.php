<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Declaration;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Child\ChildCheck;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Child\ChildEntitlementStatus;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationAmounts;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationResult;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\PreDeclarationResult;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\WithholdingTax;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Message\NonBlockingMessage;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Rate;

/**
 * Result value objects: holders without any rule (response values are never rejected, ADR 0008).
 */
final class DeclarationResultsTest extends TestCase
{
    public function testWithholdingTaxExposesEveryField(): void
    {
        $tax = WithholdingTax::of(
            DeclarationSamples::amount('400'),
            Rate::fromString('7.5'),
            DeclarationSamples::amount('30'),
            DeclarationSamples::amount('370'),
        );

        static::assertSame('400.00', $tax->taxableNetSalary()?->toDecimalString());
        static::assertSame('7.5', $tax->rate()?->toString());
        static::assertSame('30.00', $tax->taxWithheld()?->toDecimalString());
        static::assertSame('370.00', $tax->netPayableAfterTax()?->toDecimalString());
    }

    public function testWithholdingTaxMayBeEmpty(): void
    {
        $tax = WithholdingTax::of(null, null, null, null);

        static::assertNull($tax->taxableNetSalary());
        static::assertNull($tax->rate());
        static::assertNull($tax->taxWithheld());
        static::assertNull($tax->netPayableAfterTax());
    }

    public function testAmountsExposeEveryField(): void
    {
        $tax = WithholdingTax::of(null, null, null, null);
        $amounts = DeclarationAmounts::of(
            DeclarationSamples::amount('1'),
            DeclarationSamples::amount('2'),
            DeclarationSamples::amount('3'),
            $tax,
            DeclarationSamples::amount('4'),
            DeclarationSamples::amount('5'),
            DeclarationSamples::amount('6'),
            DeclarationSamples::amount('-7'),
            DeclarationSamples::date('2026-11-10'),
            DeclarationSamples::date('2026-11-12'),
        );

        static::assertSame('1.00', $amounts->contributions()?->toDecimalString());
        static::assertSame('2.00', $amounts->coveredByFamilyBenefits()?->toDecimalString());
        static::assertSame('3.00', $amounts->employerShare()?->toDecimalString());
        static::assertSame($tax, $amounts->withholding());
        static::assertSame('4.00', $amounts->cmgRemuneration()?->toDecimalString());
        static::assertSame('5.00', $amounts->payableToEmployee()?->toDecimalString());
        static::assertSame('6.00', $amounts->payableByPaje()?->toDecimalString());
        static::assertSame('-7.00', $amounts->debitedByPaje()?->toDecimalString());
        static::assertSame('2026-11-10', $amounts->plannedDebitDate()?->toString());
        static::assertSame('2026-11-12', $amounts->plannedPaymentDate()?->toString());
    }

    public function testAmountsMayBeEmpty(): void
    {
        $amounts = self::emptyAmounts();

        static::assertNull($amounts->contributions());
        static::assertNull($amounts->coveredByFamilyBenefits());
        static::assertNull($amounts->employerShare());
        static::assertNull($amounts->cmgRemuneration());
        static::assertNull($amounts->payableToEmployee());
        static::assertNull($amounts->payableByPaje());
        static::assertNull($amounts->debitedByPaje());
        static::assertNull($amounts->plannedDebitDate());
        static::assertNull($amounts->plannedPaymentDate());
    }

    public function testPreDeclarationResultExposesEveryField(): void
    {
        $amounts = self::emptyAmounts();
        $check = ChildCheck::of(DeclarationSamples::child(), ChildEntitlementStatus::of(1));
        $message = NonBlockingMessage::of('ER_API_DECLA_NB_0010', null, null);

        $result = PreDeclarationResult::of($amounts, true, DeclarationSamples::date('2026-11-15'), [$check], [$message]);

        static::assertSame($amounts, $result->amounts());
        static::assertTrue($result->isPajemploiPlus());
        static::assertSame('2026-11-15', $result->transferDate()?->toString());
        static::assertSame([$check], $result->childChecks());
        static::assertSame([$message], $result->messages());
    }

    public function testPreDeclarationResultMayBeEmpty(): void
    {
        $result = PreDeclarationResult::of(null, false, null, [], []);

        static::assertNotInstanceOf(DeclarationAmounts::class, $result->amounts());
        static::assertFalse($result->isPajemploiPlus());
        static::assertNull($result->transferDate());
        static::assertSame([], $result->childChecks());
        static::assertSame([], $result->messages());
    }

    public function testDeclarationResultExposesEveryField(): void
    {
        $amounts = self::emptyAmounts();
        $check = ChildCheck::of(null, ChildEntitlementStatus::of(0));
        $message = NonBlockingMessage::of('ER_API_050_NB_0016', 'short', 'long');

        $result = DeclarationResult::of('REF-001', $amounts, true, DeclarationSamples::date('2026-11-15'), [$check], [$message]);

        static::assertSame('REF-001', $result->documentReference());
        static::assertSame($amounts, $result->amounts());
        static::assertTrue($result->isPajemploiPlus());
        static::assertSame('2026-11-15', $result->transferDate()?->toString());
        static::assertSame([$check], $result->childChecks());
        static::assertSame([$message], $result->messages());
    }

    public function testDeclarationResultMayBeEmpty(): void
    {
        $result = DeclarationResult::of(null, null, false, null, [], []);

        static::assertNull($result->documentReference());
        static::assertNotInstanceOf(DeclarationAmounts::class, $result->amounts());
        static::assertFalse($result->isPajemploiPlus());
        static::assertNull($result->transferDate());
        static::assertSame([], $result->childChecks());
        static::assertSame([], $result->messages());
    }

    private static function emptyAmounts(): DeclarationAmounts
    {
        return DeclarationAmounts::of(
            null,
            null,
            null,
            WithholdingTax::of(null, null, null, null),
            null,
            null,
            null,
            null,
            null,
            null,
        );
    }
}
