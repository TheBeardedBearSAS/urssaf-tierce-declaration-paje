<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Wire;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Rate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\JsonDecoder;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\RateEncoding;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\WireAssertions;

final class RateEncodingTest extends TestCase
{
    /**
     * @return iterable<string, array{int|float, string}>
     */
    public static function provideDecodedRates(): iterable
    {
        yield 'integer zero' => [0, '0'];
        yield 'integer' => [100, '100'];
        yield 'negative integer' => [-3, '-3'];
        yield 'float zero' => [0.0, '0'];
        yield 'negative float zero' => [-0.0, '0'];
        yield 'float integral' => [20.0, '20'];
        yield 'one decimal' => [7.5, '7.5'];
        yield 'official-like rate' => [4.3, '4.3'];
        yield 'many decimals' => [12.345_678, '12.345678'];
        yield 'binary noise is kept as the shortest round trip' => [0.1 + 0.2, '0.30000000000000004'];
        yield 'small exponent form' => [0.000_01, '0.00001'];
        yield 'smaller exponent form' => [1.5e-7, '0.00000015'];
        yield 'large exponent form' => [1.0e25, '10000000000000000000000000'];
        yield 'negative' => [-2.5, '-2.5'];
        yield 'tiny exponent form' => [1.0e-10, '0.0000000001'];
    }

    #[DataProvider('provideDecodedRates')]
    public function testDecode(int|float $value, string $expected): void
    {
        static::assertSame($expected, RateEncoding::decode($value, 'txPas')->toString());
    }

    public function testADigitStringBeyondTheIntegerRangeIsTakenAsIs(): void
    {
        static::assertSame('9223372036854775808', RateEncoding::decode('9223372036854775808', 'txPas')->toString());
        static::assertSame('-9223372036854775809', RateEncoding::decodeOptional('-9223372036854775809', 'txPas')?->toString());
    }

    public function testNonFiniteNumbersAreRefused(): void
    {
        foreach ([INF, -INF, NAN] as $value) {
            WireAssertions::assertUnexpected(
                'Unexpected URSSAF response: field "txPas" is not a valid rate.',
                static fn(): Rate => RateEncoding::decode($value, 'txPas'),
            );
        }
    }

    public function testDecodeOptionalKeepsAbsence(): void
    {
        static::assertNull(RateEncoding::decodeOptional(null, 'txPas'));
        static::assertSame('0', RateEncoding::decodeOptional(0, 'txPas')?->toString());
        static::assertSame('7.5', RateEncoding::decodeOptional(7.5, 'txPas')?->toString());
        WireAssertions::assertUnexpected(
            'Unexpected URSSAF response: field "txPas" is not a valid rate.',
            static fn(): ?Rate => RateEncoding::decodeOptional(NAN, 'txPas'),
        );
    }

    public function testDecodeFromAJsonDocument(): void
    {
        $payload = JsonDecoder::decode('{"a":7.50,"b":0,"c":1e-5}');

        static::assertSame('7.5', RateEncoding::decode($payload->requireNumber('a'), 'a')->toString());
        static::assertSame('0', RateEncoding::decode($payload->requireNumber('b'), 'b')->toString());
        static::assertSame('0.00001', RateEncoding::decode($payload->requireNumber('c'), 'c')->toString());
    }

    public function testAnInvalidLiteralIsRefusedWithTheCauseAndWithoutItsValue(): void
    {
        $exception = WireAssertions::caught(static fn(): Rate => RateEncoding::fromLiteral('12,5', 'txPas'));

        static::assertSame('Unexpected URSSAF response: field "txPas" is not a valid rate.', $exception->getMessage());
        static::assertInstanceOf(InvalidValue::class, $exception->getPrevious());
        static::assertSame('7.5', RateEncoding::fromLiteral('7.5', 'txPas')->toString());
    }
}
