<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Shared;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Amount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\DomainAssertions;

final class AmountComparisonTest extends TestCase
{
    public function testSignPredicates(): void
    {
        static::assertTrue(Amount::fromCents(-1)->isNegative());
        static::assertFalse(Amount::fromCents(0)->isNegative());
        static::assertFalse(Amount::fromCents(1)->isNegative());

        static::assertFalse(Amount::fromCents(-1)->isZero());
        static::assertTrue(Amount::fromCents(0)->isZero());
        static::assertFalse(Amount::fromCents(1)->isZero());

        static::assertFalse(Amount::fromCents(-1)->isPositive());
        static::assertFalse(Amount::fromCents(0)->isPositive());
        static::assertTrue(Amount::fromCents(1)->isPositive());
    }

    public function testComparisons(): void
    {
        $one = Amount::fromCents(100);
        $two = Amount::fromCents(200);
        $sameAsOne = Amount::fromCents(100);

        static::assertTrue($two->isGreaterThan($one));
        static::assertFalse($one->isGreaterThan($two));
        static::assertFalse($one->isGreaterThan($sameAsOne));

        static::assertTrue($two->isGreaterThanOrEqual($one));
        static::assertTrue($one->isGreaterThanOrEqual($sameAsOne));
        static::assertFalse($one->isGreaterThanOrEqual($two));

        static::assertTrue($one->isLessThan($two));
        static::assertFalse($two->isLessThan($one));
        static::assertFalse($one->isLessThan($sameAsOne));

        static::assertTrue($one->isLessThanOrEqual($two));
        static::assertTrue($one->isLessThanOrEqual($sameAsOne));
        static::assertFalse($two->isLessThanOrEqual($one));

        static::assertTrue($one->equals($sameAsOne));
        static::assertFalse($one->equals($two));
        static::assertTrue(Amount::fromCents(-5)->isLessThan(Amount::fromCents(-4)));
    }

    public function testAdd(): void
    {
        static::assertSame(300, Amount::fromCents(100)->add(Amount::fromCents(200))->cents());
        static::assertSame(-100, Amount::fromCents(100)->add(Amount::fromCents(-200))->cents());
        static::assertSame(100, Amount::fromCents(100)->add(Amount::fromCents(0))->cents());
        static::assertSame(PHP_INT_MAX, Amount::fromCents(PHP_INT_MAX - 1)->add(Amount::fromCents(1))->cents());
        static::assertSame(PHP_INT_MIN, Amount::fromCents(PHP_INT_MIN + 1)->add(Amount::fromCents(-1))->cents());
        static::assertSame(PHP_INT_MIN, Amount::fromCents(PHP_INT_MIN)->add(Amount::fromCents(0))->cents());
        static::assertSame(-1, Amount::fromCents(PHP_INT_MAX)->add(Amount::fromCents(PHP_INT_MIN))->cents());
    }

    public function testAddRejectsPositiveOverflow(): void
    {
        static::assertSame('amount.too_large', DomainAssertions::assertViolationCode('amount.too_large', static fn(): Amount => Amount::fromCents(PHP_INT_MAX)->add(Amount::fromCents(1)))->violationCode());
    }

    public function testAddRejectsNegativeOverflow(): void
    {
        static::assertSame('amount.too_large', DomainAssertions::assertViolationCode('amount.too_large', static fn(): Amount => Amount::fromCents(PHP_INT_MIN)->add(Amount::fromCents(-1)))->violationCode());
    }
}
