<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Estimate;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Estimate\YoungestChildAge;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\DomainAssertions;

final class YoungestChildAgeTest extends TestCase
{
    public function testDocumentedValues(): void
    {
        static::assertSame('1', YoungestChildAge::underThree()->value());
        static::assertSame('2', YoungestChildAge::threeToSix()->value());
        static::assertSame('3', YoungestChildAge::overSix()->value());
        static::assertTrue(YoungestChildAge::underThree()->isDocumented());
        static::assertTrue(YoungestChildAge::threeToSix()->isDocumented());
        static::assertTrue(YoungestChildAge::overSix()->isDocumented());
    }

    #[DataProvider('provideDocumentedCodes')]
    public function testDocumentedCodeIsRecognised(string $code): void
    {
        static::assertTrue(YoungestChildAge::of($code)->isDocumented());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideDocumentedCodes(): iterable
    {
        yield 'under three' => ['1'];
        yield 'three to six' => ['2'];
        yield 'over six' => ['3'];
    }

    public function testAnyOtherOneCharacterCodeIsAcceptedButNotDocumented(): void
    {
        $age = YoungestChildAge::of('9');

        static::assertSame('9', $age->value());
        static::assertFalse($age->isDocumented());
    }

    public function testMultiByteCharacterCountsAsOneCharacter(): void
    {
        static::assertSame('é', YoungestChildAge::of('é')->value());
    }

    public function testEmptyCodeIsTooShort(): void
    {
        $exception = DomainAssertions::assertViolationCode(
            'youngest_child_age.too_short',
            static fn(): YoungestChildAge => YoungestChildAge::of(''),
        );

        static::assertSame(['min_length' => 1], $exception->params());
    }

    public function testTwoCharactersAreTooLong(): void
    {
        $exception = DomainAssertions::assertViolationCode(
            'youngest_child_age.too_long',
            static fn(): YoungestChildAge => YoungestChildAge::of('12'),
        );

        static::assertSame(['max_length' => 1], $exception->params());
    }

    public function testEquality(): void
    {
        static::assertTrue(YoungestChildAge::underThree()->equals(YoungestChildAge::of('1')));
        static::assertFalse(YoungestChildAge::underThree()->equals(YoungestChildAge::overSix()));
    }
}
