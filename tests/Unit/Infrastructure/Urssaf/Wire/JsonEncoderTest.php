<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Wire;

use JsonException;
use PHPUnit\Framework\TestCase;
use stdClass;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\JsonDecimal;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\JsonEncoder;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\JsonObject;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\DomainAssertions;

final class JsonEncoderTest extends TestCase
{
    public function testScalars(): void
    {
        static::assertSame('null', JsonEncoder::encode(null));
        static::assertSame('true', JsonEncoder::encode(true));
        static::assertSame('false', JsonEncoder::encode(false));
        static::assertSame('12', JsonEncoder::encode(12));
        static::assertSame('"a"', JsonEncoder::encode('a'));
    }

    public function testStringsAreNotEscapedMoreThanNeeded(): void
    {
        static::assertSame('"L\'HOMME é/x"', JsonEncoder::encode("L'HOMME é/x"));
        static::assertSame('"a\"b"', JsonEncoder::encode('a"b'));
    }

    public function testDecimalIsEmittedVerbatim(): void
    {
        static::assertSame('11.02', JsonEncoder::encode(JsonDecimal::fromString('11.02')));
        static::assertSame('{"mnt":12.50}', JsonEncoder::encode(['mnt' => JsonDecimal::fromString('12.50')]));
        static::assertSame('[0.10]', JsonEncoder::encode([JsonDecimal::fromString('0.10')]));
    }

    public function testListsAreArraysAndMapsAreObjects(): void
    {
        static::assertSame('[]', JsonEncoder::encode([]));
        static::assertSame('[1,"a",null,true]', JsonEncoder::encode([1, 'a', null, true]));
        static::assertSame('{"a":1,"b":"x"}', JsonEncoder::encode(['a' => 1, 'b' => 'x']));
        static::assertSame('{"1":"a","3":"b"}', JsonEncoder::encode([1 => 'a', 3 => 'b']));
        static::assertSame('{"a":[{"b":[]}]}', JsonEncoder::encode(['a' => [['b' => []]]]));
    }

    public function testAnEmptyJsonObjectIsForcedToBraces(): void
    {
        static::assertSame('{}', JsonEncoder::encode(JsonObject::of([])));
        static::assertSame('{"a":{}}', JsonEncoder::encode(['a' => JsonObject::of([])]));
        static::assertSame('[{}]', JsonEncoder::encode([JsonObject::of([])]));
        static::assertSame('{"a":1,"b":[2]}', JsonEncoder::encode(JsonObject::of(['a' => 1, 'b' => [2]])));
    }

    public function testFloatsAreRefusedAtAnyDepth(): void
    {
        self::assertRefused(['a' => 1.5]);
        self::assertRefused([1.5]);
        self::assertRefused(['a' => ['b' => 0.0]]);
        self::assertRefused(JsonObject::of(['a' => 1.0]));
    }

    public function testOtherObjectsAreRefused(): void
    {
        self::assertRefused(['a' => new stdClass()]);
    }

    /**
     * @param array<array-key, mixed>|JsonObject $value
     */
    private static function assertRefused(array|JsonObject $value): void
    {
        $exception = DomainAssertions::assertViolationCode('json_encoder.unsupported_value', static fn(): string => JsonEncoder::encode($value));

        static::assertSame('Only null, bool, int, string, arrays, JsonDecimal and JsonObject can be encoded.', $exception->getMessage());
    }

    public function testInvalidUtf8IsRefused(): void
    {
        $this->expectException(JsonException::class);

        JsonEncoder::encode("\xB1");
    }
}
