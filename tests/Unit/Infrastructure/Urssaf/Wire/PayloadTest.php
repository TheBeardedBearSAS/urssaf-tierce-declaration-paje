<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Wire;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\JsonDecoder;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\Payload;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\WireAssertions;

/**
 * @mago-expect lint:too-many-methods
 * @mago-expect lint:kan-defect
 */
final class PayloadTest extends TestCase
{
    private const PREFIX = 'Unexpected URSSAF response: ';

    private static function failure(string $key, string $reason): string
    {
        return \sprintf('Unexpected URSSAF response: field "%s" %s.', $key, $reason);
    }

    public function testFromDecodedAcceptsObjectsAndListsOnly(): void
    {
        static::assertTrue(Payload::fromDecoded([])->isEmpty());
        static::assertSame('x', Payload::fromDecoded(['a' => 'x'])->string('a'));

        foreach (['x', 1, 1.5, true, null] as $scalar) {
            WireAssertions::assertUnexpected(
                self::PREFIX . 'body is neither a JSON object nor a JSON array.',
                static fn(): Payload => Payload::fromDecoded($scalar),
            );
        }
    }

    public function testDebugInfoShowsKeysOnly(): void
    {
        $payload = JsonDecoder::decode('{"nirSalarie":"178010701005265","enfants":[{"nom":"DUPONT"}],"7":1}');

        static::assertSame(['nirSalarie' => '***', 'enfants' => '***', '7' => '***'], $payload->__debugInfo());
        static::assertStringNotContainsString('178010701005265', print_r($payload, true));
        static::assertStringNotContainsString('DUPONT', print_r($payload, true));
    }

    public function testEmptyAndShape(): void
    {
        static::assertTrue(Payload::empty()->isEmpty());
        static::assertTrue(Payload::empty()->isList());
        static::assertFalse(Payload::fromDecoded(['a' => 1])->isEmpty());
        static::assertFalse(Payload::fromDecoded(['a' => 1])->isList());
        static::assertTrue(Payload::fromDecoded([1, 2])->isList());
        static::assertFalse(Payload::fromDecoded([1 => 'a'])->isList());
    }

    public function testHasIsTrueForAPresentKeyEvenWhenNull(): void
    {
        $payload = Payload::fromDecoded(['a' => null, 'b' => 0, 'c' => '']);

        static::assertTrue($payload->has('a'));
        static::assertTrue($payload->has('b'));
        static::assertTrue($payload->has('c'));
        static::assertFalse($payload->has('d'));
    }

    public function testLenientString(): void
    {
        $payload = Payload::fromDecoded(['s' => 'x', 'e' => '', 'n' => null, 'i' => 1, 'a' => ['x']]);

        static::assertSame('x', $payload->string('s'));
        static::assertSame('', $payload->string('e'));
        static::assertNull($payload->string('n'));
        static::assertNull($payload->string('i'));
        static::assertNull($payload->string('a'));
        static::assertNull($payload->string('absent'));
    }

    public function testOptionalString(): void
    {
        $payload = Payload::fromDecoded(['s' => 'x', 'e' => '', 'n' => null, 'i' => 1]);

        static::assertSame('x', $payload->optionalString('s'));
        static::assertSame('', $payload->optionalString('e'));
        static::assertNull($payload->optionalString('n'));
        static::assertNull($payload->optionalString('absent'));
        WireAssertions::assertUnexpected(self::PREFIX . 'field "i" is not a string.', static fn(): ?string => $payload->optionalString('i'));
    }

    public function testRequireString(): void
    {
        $payload = Payload::fromDecoded(['s' => 'x', 'e' => '', 'n' => null, 'i' => 1]);

        static::assertSame('x', $payload->requireString('s'));
        foreach (['e', 'n', 'i', 'absent'] as $key) {
            WireAssertions::assertUnexpected(
                self::failure($key, 'is missing or empty'),
                static fn(): string => $payload->requireString($key),
            );
        }
    }

    public function testOptionalBool(): void
    {
        $payload = Payload::fromDecoded(['t' => true, 'f' => false, 'n' => null, 's' => 'true', 'i' => 0]);

        static::assertTrue($payload->optionalBool('t'));
        static::assertFalse($payload->optionalBool('f'));
        static::assertNull($payload->optionalBool('n'));
        static::assertNull($payload->optionalBool('absent'));
        WireAssertions::assertUnexpected(self::PREFIX . 'field "s" is not a boolean.', static fn(): ?bool => $payload->optionalBool('s'));
        WireAssertions::assertUnexpected(self::PREFIX . 'field "i" is not a boolean.', static fn(): ?bool => $payload->optionalBool('i'));
    }

    public function testRequireBool(): void
    {
        $payload = Payload::fromDecoded(['t' => true, 'f' => false, 'n' => null, 's' => 'true']);

        static::assertTrue($payload->requireBool('t'));
        static::assertFalse($payload->requireBool('f'));
        foreach (['n', 's', 'absent'] as $key) {
            WireAssertions::assertUnexpected(
                self::failure($key, 'is missing or not a boolean'),
                static fn(): bool => $payload->requireBool($key),
            );
        }
    }

    public function testInt(): void
    {
        $payload = Payload::fromDecoded(['i' => 3, 'z' => 0, 'n' => null, 'f' => 1.5, 's' => '3', 'w' => 3.0]);

        static::assertSame(3, $payload->int('i'));
        static::assertSame(0, $payload->int('z'));
        static::assertNull($payload->int('n'));
        static::assertNull($payload->int('absent'));
        foreach (['f', 's', 'w'] as $key) {
            WireAssertions::assertUnexpected(self::failure($key, 'is not an integer'), static fn(): ?int => $payload->int($key));
        }
    }

    public function testRequireInt(): void
    {
        $payload = Payload::fromDecoded(['i' => 3, 'z' => 0, 'n' => null, 'f' => 1.5]);

        static::assertSame(3, $payload->requireInt('i'));
        static::assertSame(0, $payload->requireInt('z'));
        foreach (['n', 'f', 'absent'] as $key) {
            WireAssertions::assertUnexpected(
                self::failure($key, 'is missing or not an integer'),
                static fn(): int => $payload->requireInt($key),
            );
        }
    }

    public function testNumber(): void
    {
        $payload = Payload::fromDecoded(['i' => 3, 'f' => 1.5, 'z' => 0, 'n' => null, 's' => '3', 'b' => true]);

        static::assertSame(3, $payload->number('i'));
        static::assertSame(1.5, $payload->number('f'));
        static::assertSame(0, $payload->number('z'));
        static::assertNull($payload->number('n'));
        static::assertNull($payload->number('absent'));
        foreach (['s', 'b'] as $key) {
            WireAssertions::assertUnexpected(self::failure($key, 'is not a number'), static fn(): int|float|null => $payload->number($key));
        }
    }

    public function testRequireNumber(): void
    {
        $payload = Payload::fromDecoded(['i' => 3, 'f' => 1.5, 'z' => 0, 'n' => null, 's' => '3']);

        static::assertSame(3, $payload->requireNumber('i'));
        static::assertSame(1.5, $payload->requireNumber('f'));
        static::assertSame(0, $payload->requireNumber('z'));
        foreach (['n', 's', 'absent'] as $key) {
            WireAssertions::assertUnexpected(
                self::failure($key, 'is missing or not a number'),
                static fn(): int|float => $payload->requireNumber($key),
            );
        }
    }

    public function testNumericAcceptsNumbersAndBigIntegerDigitStrings(): void
    {
        $payload = Payload::fromDecoded([
            'i' => 3,
            'f' => 1.5,
            'big' => '123456789012345678901234567890',
            'neg' => '-123456789012345678901234567890',
            'n' => null,
            'text' => 'abc',
            'decimal' => '1.5',
            'empty' => '',
            'newline' => "12\n",
            'b' => true,
        ]);

        static::assertSame(3, $payload->numeric('i'));
        static::assertSame(1.5, $payload->numeric('f'));
        static::assertSame('123456789012345678901234567890', $payload->numeric('big'));
        static::assertSame('-123456789012345678901234567890', $payload->numeric('neg'));
        static::assertNull($payload->numeric('n'));
        static::assertNull($payload->numeric('absent'));
        foreach (['text', 'decimal', 'empty', 'newline', 'b'] as $key) {
            WireAssertions::assertUnexpected(self::failure($key, 'is not a number'), static fn(): int|float|string|null => $payload->numeric($key));
        }
    }

    public function testRequireNumeric(): void
    {
        $payload = Payload::fromDecoded(['i' => 0, 'big' => '123456789012345678901234567890', 'n' => null, 'text' => 'abc']);

        static::assertSame(0, $payload->requireNumeric('i'));
        static::assertSame('123456789012345678901234567890', $payload->requireNumeric('big'));
        foreach (['n', 'absent'] as $key) {
            WireAssertions::assertUnexpected(
                self::failure($key, 'is missing or not a number'),
                static fn(): int|float|string => $payload->requireNumeric($key),
            );
        }
        WireAssertions::assertUnexpected(self::PREFIX . 'field "text" is not a number.', static fn(): int|float|string => $payload->requireNumeric('text'));
    }

    public function testObject(): void
    {
        $payload = Payload::fromDecoded(['o' => ['a' => 'x'], 'e' => [], 'n' => null, 's' => 'x']);

        static::assertSame('x', $payload->object('o')?->string('a'));
        static::assertTrue($payload->object('e')?->isEmpty());
        static::assertNull($payload->object('n'));
        static::assertNull($payload->object('absent'));
        WireAssertions::assertUnexpected(self::PREFIX . 'field "s" is not an object.', static fn(): ?Payload => $payload->object('s'));
    }

    public function testAFilledListIsNotAnObject(): void
    {
        $payload = Payload::fromDecoded(['l' => [1, 2], 'one' => [['a' => 'x']], 'e' => []]);

        WireAssertions::assertUnexpected(self::PREFIX . 'field "l" is not an object.', static fn(): ?Payload => $payload->object('l'));
        WireAssertions::assertUnexpected(self::PREFIX . 'field "one" is not an object.', static fn(): ?Payload => $payload->object('one'));
        WireAssertions::assertUnexpected(self::failure('l', 'is missing or not an object'), static fn(): Payload => $payload->requireObject('l'));
        WireAssertions::assertUnexpected(self::failure('one', 'is missing or not an object'), static fn(): Payload => $payload->requireObject('one'));
        static::assertTrue($payload->object('e')?->isEmpty());
        static::assertTrue($payload->requireObject('e')->isEmpty());
    }

    public function testRequireObject(): void
    {
        $payload = Payload::fromDecoded(['o' => ['a' => 'x'], 'e' => [], 'n' => null, 's' => 'x']);

        static::assertSame('x', $payload->requireObject('o')->string('a'));
        static::assertTrue($payload->requireObject('e')->isEmpty());
        foreach (['n', 's', 'absent'] as $key) {
            WireAssertions::assertUnexpected(
                self::failure($key, 'is missing or not an object'),
                static fn(): Payload => $payload->requireObject($key),
            );
        }
    }

    public function testItems(): void
    {
        $payload = Payload::fromDecoded([
            'list' => [['a' => 'x'], ['a' => 'y']],
            'empty' => [],
            'n' => null,
            'map' => ['a' => ['b' => 1]],
            'scalars' => [1, 2],
            'mixed' => [['a' => 'x'], 'y'],
            's' => 'x',
        ]);

        static::assertSame(['x', 'y'], array_map(static fn(Payload $item): ?string => $item->string('a'), $payload->items('list')));
        static::assertSame([], $payload->items('empty'));
        static::assertSame([], $payload->items('n'));
        static::assertSame([], $payload->items('absent'));
        WireAssertions::assertUnexpected(self::PREFIX . 'field "map" is not a JSON array.', static fn(): array => $payload->items('map'));
        WireAssertions::assertUnexpected(self::PREFIX . 'field "scalars" is not a JSON array of objects.', static fn(): array => $payload->items('scalars'));
        WireAssertions::assertUnexpected(self::PREFIX . 'field "mixed" is not a JSON array of objects.', static fn(): array => $payload->items('mixed'));
        WireAssertions::assertUnexpected(self::PREFIX . 'field "s" is not a JSON array.', static fn(): array => $payload->items('s'));
    }

    public function testEntries(): void
    {
        static::assertSame([], Payload::empty()->entries());
        static::assertSame(['x', 'y'], array_map(
            static fn(Payload $item): ?string => $item->string('a'),
            JsonDecoder::decode('[{"a":"x"},{"a":"y"}]')->entries(),
        ));
        WireAssertions::assertUnexpected(self::PREFIX . 'body is not a JSON array.', static fn(): array => JsonDecoder::decode('{"a":{}}')->entries());
        WireAssertions::assertUnexpected(self::PREFIX . 'body is not a JSON array of objects.', static fn(): array => JsonDecoder::decode('[1]')->entries());
        WireAssertions::assertUnexpected(self::PREFIX . 'body is not a JSON array of objects.', static fn(): array => JsonDecoder::decode('["a",{}]')->entries());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providePersonalValues(): iterable
    {
        yield 'string' => ['PERSONAL-VALUE'];
        yield 'number-like' => ['123456789012345678901234567890'];
    }

    #[DataProvider('providePersonalValues')]
    public function testMessagesNeverContainTheValue(string $value): void
    {
        $payload = Payload::fromDecoded(['f' => [$value], 'g' => $value]);

        $messages = [
            WireAssertions::caught(static fn(): mixed => $payload->optionalString('f'))->getMessage(),
            WireAssertions::caught(static fn(): mixed => $payload->optionalBool('g'))->getMessage(),
            WireAssertions::caught(static fn(): mixed => $payload->requireBool('g'))->getMessage(),
            WireAssertions::caught(static fn(): mixed => $payload->int('g'))->getMessage(),
            WireAssertions::caught(static fn(): mixed => $payload->number('f'))->getMessage(),
            WireAssertions::caught(static fn(): mixed => $payload->numeric('f'))->getMessage(),
            WireAssertions::caught(static fn(): mixed => $payload->object('g'))->getMessage(),
            WireAssertions::caught(static fn(): mixed => $payload->items('f'))->getMessage(),
            WireAssertions::caught(static fn(): mixed => $payload->items('g'))->getMessage(),
        ];

        foreach ($messages as $message) {
            static::assertStringNotContainsString($value, $message);
        }
    }

    public function testRequireJsonObject(): void
    {
        Payload::emptyObject()->requireJsonObject();
        Payload::fromDecoded(['a' => 1])->requireJsonObject();
        static::assertTrue(Payload::emptyObject()->isEmpty());

        foreach ([Payload::empty(), Payload::fromDecoded([]), Payload::fromDecoded([1, 2]), Payload::fromDecoded([['a' => 1]])] as $payload) {
            WireAssertions::assertUnexpected(self::PREFIX . 'body is not a JSON object.', static function () use ($payload): void {
                $payload->requireJsonObject();
            });
        }
    }

    public function testNumericRefusesADigitStringThatFitsAnInteger(): void
    {
        $payload = Payload::fromDecoded([
            'small' => '500',
            'max' => '9223372036854775807',
            'min' => '-9223372036854775808',
            'above' => '9223372036854775808',
            'below' => '-9223372036854775809',
            'zeros' => '0500',
        ]);

        foreach (['small', 'max', 'min', 'zeros'] as $key) {
            WireAssertions::assertUnexpected(self::failure($key, 'is not a number'), static fn(): int|float|string|null => $payload->numeric($key));
        }
        static::assertSame('9223372036854775808', $payload->numeric('above'));
        static::assertSame('-9223372036854775809', $payload->numeric('below'));
    }

    public function testLenientEntriesSkipWhatIsNotAnObject(): void
    {
        $payload = Payload::fromDecoded([['a' => 'x'], 5, 'y', null, ['a' => 'z'], []]);

        static::assertSame(['x', 'z', null], array_map(static fn(Payload $entry): ?string => $entry->string('a'), $payload->lenientEntries()));
        static::assertSame([], Payload::fromDecoded(['a' => ['b' => 'x']])->lenientEntries());
        static::assertSame([], Payload::empty()->lenientEntries());
    }

    public function testLenientItemsReadAListOfObjectsOrNothing(): void
    {
        $payload = Payload::fromDecoded([
            'ok' => [['a' => 'x'], 7, ['a' => 'y']],
            'map' => ['k' => ['a' => 'x']],
            'scalar' => 'x',
            'n' => null,
        ]);

        static::assertSame(['x', 'y'], array_map(static fn(Payload $item): ?string => $item->string('a'), $payload->lenientItems('ok')));
        static::assertSame([], $payload->lenientItems('map'));
        static::assertSame([], $payload->lenientItems('scalar'));
        static::assertSame([], $payload->lenientItems('n'));
        static::assertSame([], $payload->lenientItems('absent'));
    }
}
