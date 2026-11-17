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
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\DomainAssertions;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\Fixtures;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\WireAssertions;

/**
 * @mago-expect lint:too-many-methods
 */
final class JsonDecoderTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function provideBlankBodies(): iterable
    {
        yield 'empty' => [''];
        yield 'spaces' => ['   '];
        yield 'newline' => ["\n"];
        yield 'tab and CRLF' => ["\t\r\n"];
    }

    #[DataProvider('provideBlankBodies')]
    public function testABlankBodyIsAnEmptyPayload(string $body): void
    {
        static::assertTrue(JsonDecoder::decode($body)->isEmpty());
    }

    public function testObjectsAndArraysAreDecoded(): void
    {
        $object = JsonDecoder::decode('{"a":"x"}');
        $list = JsonDecoder::decode('[{"a":"x"}]');

        static::assertFalse($object->isEmpty());
        static::assertSame('x', $object->string('a'));
        static::assertFalse($object->isList());
        static::assertTrue($list->isList());
        static::assertCount(1, $list->entries());
    }

    public function testAnEmptyJsonObjectIsEmpty(): void
    {
        static::assertTrue(JsonDecoder::decode('{}')->isEmpty());
        static::assertTrue(JsonDecoder::decode('[]')->isEmpty());
    }

    public function testBigIntegersStayDigitStrings(): void
    {
        $payload = JsonDecoder::decode('{"mnt":123456789012345678901234567890,"n":12,"f":1.5}');

        static::assertSame('123456789012345678901234567890', $payload->requireNumeric('mnt'));
        static::assertSame(12, $payload->requireNumber('n'));
        static::assertSame(1.5, $payload->requireNumber('f'));
    }

    public function testEveryOfficialFixtureIsDecoded(): void
    {
        // The `_valid` variants (tests/Fixtures/wire/README.md) are not official examples.
        $official = array_values(array_filter(
            Fixtures::names(),
            static fn(string $name): bool => !str_ends_with($name, '_valid'),
        ));
        static::assertCount(31, $official);
        foreach ($official as $name) {
            static::assertNotSame('', Fixtures::load($name), $name);
            static::assertSame($name === 'verify_employee_request', JsonDecoder::decode(Fixtures::load($name))->isEmpty(), $name);
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideInvalidBodies(): iterable
    {
        yield 'invalid json' => ['{"a":', 'Unexpected URSSAF response: body is not valid JSON.'];
        yield 'plain text' => ['Service Unavailable', 'Unexpected URSSAF response: body is not valid JSON.'];
        yield 'html' => ['<html></html>', 'Unexpected URSSAF response: body is not valid JSON.'];
        yield 'string' => ['"x"', 'Unexpected URSSAF response: body is neither a JSON object nor a JSON array.'];
        yield 'number' => ['12', 'Unexpected URSSAF response: body is neither a JSON object nor a JSON array.'];
        yield 'null' => ['null', 'Unexpected URSSAF response: body is neither a JSON object nor a JSON array.'];
        yield 'boolean' => ['true', 'Unexpected URSSAF response: body is neither a JSON object nor a JSON array.'];
    }

    #[DataProvider('provideInvalidBodies')]
    public function testInvalidBodiesAreRefused(string $body, string $message): void
    {
        WireAssertions::assertUnexpected($message, static fn(): mixed => JsonDecoder::decode($body));
    }

    public function testTheJsonErrorIsNotChained(): void
    {
        $exception = WireAssertions::caught(static fn(): mixed => JsonDecoder::decode('{"nirSalarie":"178010701005265"'));

        static::assertSame('Unexpected URSSAF response: body is not valid JSON.', $exception->getMessage());
        static::assertNull($exception->getPrevious());
    }

    public function testTheRawBodyIsSensitive(): void
    {
        DomainAssertions::assertSensitiveParameters(JsonDecoder::class, 'decode', ['json']);
    }

    public function testNestingBeyondTheDepthLimitIsRefused(): void
    {
        $body = str_repeat('[', 512) . str_repeat(']', 512);

        WireAssertions::assertUnexpected('Unexpected URSSAF response: body is not valid JSON.', static fn(): mixed => JsonDecoder::decode($body));
        static::assertFalse(JsonDecoder::decode(str_repeat('[', 511) . str_repeat(']', 511))->isEmpty());
    }

    public function testOnlyAJsonObjectIsAnObject(): void
    {
        foreach (['{}', ' {} ', "\n{\n}", '{"a":1}'] as $body) {
            JsonDecoder::decode($body)->requireJsonObject();
        }

        foreach (['', '  ', '[]', ' [] ', '[1]', '[{"a":1}]'] as $body) {
            WireAssertions::assertUnexpected(
                'Unexpected URSSAF response: body is not a JSON object.',
                static function () use ($body): void {
                    JsonDecoder::decode($body)->requireJsonObject();
                },
            );
        }
    }
}
