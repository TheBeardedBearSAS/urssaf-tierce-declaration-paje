<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Declaration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\CalculationMode;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\DomainAssertions;

final class CalculationModeTest extends TestCase
{
    public function testDocumentedModes(): void
    {
        static::assertSame('R', CalculationMode::real()->value());
        static::assertSame('F', CalculationMode::flatRate()->value());
        static::assertSame('S', CalculationMode::specific()->value());
        static::assertTrue(CalculationMode::real()->isDocumented());
        static::assertTrue(CalculationMode::flatRate()->isDocumented());
        static::assertTrue(CalculationMode::specific()->isDocumented());
    }

    #[DataProvider('provideDocumentedCodes')]
    public function testDocumentedCodeIsRecognised(string $code): void
    {
        static::assertTrue(CalculationMode::of($code)->isDocumented());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideDocumentedCodes(): iterable
    {
        yield 'real' => ['R'];
        yield 'flat rate' => ['F'];
        yield 'specific' => ['S'];
    }

    public function testAnyOtherOneCharacterCodeIsAcceptedButNotDocumented(): void
    {
        $mode = CalculationMode::of('X');

        static::assertSame('X', $mode->value());
        static::assertFalse($mode->isDocumented());
    }

    public function testMultiByteCharacterCountsAsOneCharacter(): void
    {
        static::assertSame('é', CalculationMode::of('é')->value());
    }

    public function testEmptyCodeIsTooShort(): void
    {
        $exception = DomainAssertions::assertViolationCode(
            'calculation_mode.too_short',
            static fn(): CalculationMode => CalculationMode::of(''),
        );

        static::assertSame(['min_length' => 1], $exception->params());
    }

    public function testTwoCharactersAreTooLong(): void
    {
        $exception = DomainAssertions::assertViolationCode(
            'calculation_mode.too_long',
            static fn(): CalculationMode => CalculationMode::of('RF'),
        );

        static::assertSame(['max_length' => 1], $exception->params());
    }

    public function testEquality(): void
    {
        static::assertTrue(CalculationMode::real()->equals(CalculationMode::of('R')));
        static::assertFalse(CalculationMode::real()->equals(CalculationMode::flatRate()));
    }
}
