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

use JsonException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\ErrorPayloadParser;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\JsonDecoder;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\Payload;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\DomainAssertions;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\Fixtures;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\WireAssertions;

/**
 * @mago-expect lint:too-many-methods
 */
final class ErrorPayloadParserTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function provideErrorFixtures(): iterable
    {
        foreach (Fixtures::names('error_') as $name) {
            yield $name => [$name];
        }
    }

    public function testThereAreFifteenOfficialErrorFixtures(): void
    {
        static::assertCount(15, iterator_to_array(self::provideErrorFixtures(), false));
    }

    /**
     * @throws JsonException
     */
    #[DataProvider('provideErrorFixtures')]
    public function testASingleObjectIsParsed(string $name): void
    {
        $expected = Fixtures::json($name);

        foreach ([
            (new ErrorPayloadParser())->parseBody(Fixtures::load($name)),
            (new ErrorPayloadParser())->parsePayload(JsonDecoder::decode(Fixtures::load($name))),
        ] as $errors) {
            static::assertCount(1, $errors);
            $error = $errors->first();
            static::assertNotNull($error);
            static::assertSame($expected['code'], $error->code());
            static::assertSame($expected['message'], $error->message());
            static::assertSame($expected['description'], $error->description());
        }
    }

    /**
     * @throws JsonException
     */
    #[DataProvider('provideErrorFixtures')]
    public function testAListAndAnEnvelopeAreParsedLikeTheSingleObject(string $name): void
    {
        $expected = (new ErrorPayloadParser())->parseBody(Fixtures::load($name));
        $single = Fixtures::load($name);

        $list = (new ErrorPayloadParser())->parseBody('[' . $single . ']');
        $envelope = (new ErrorPayloadParser())->parseBody('{"errors":[' . $single . ']}');

        static::assertEquals($expected, $list);
        static::assertEquals($expected, $envelope);
        static::assertSame($expected->codes(), $list->codes());
    }

    public function testAListKeepsEveryEntryInOrder(): void
    {
        $errors = (new ErrorPayloadParser())->parseBody('[' . Fixtures::load('error_param_invalide') . ',' . Fixtures::load('error_technical') . ']');

        static::assertSame(['PARAM_INVALIDE', 'ERR_TECHNIQUE'], $errors->codes());
    }

    public function testCodesAreNormalisedButTextsAreKeptRaw(): void
    {
        $errors = (new ErrorPayloadParser())->parseBody("{\"code\":\" PE_INEXISTANT \\n\",\"message\":\"  Employeur\u{a0}inexistant \",\"description\":\" d \"}");

        $error = $errors->first();
        static::assertNotNull($error);
        static::assertSame('PE_INEXISTANT', $error->code());
        static::assertSame("  Employeur\u{a0}inexistant ", $error->message());
        static::assertSame(' d ', $error->description());
    }

    public function testInnerWhitespaceOfACodeIsRemoved(): void
    {
        $errors = (new ErrorPayloadParser())->parseBody('{"code":"ER_API_050_00\n 10"}');

        static::assertSame(['ER_API_050_0010'], $errors->codes());
    }

    public function testMissingMessageAndDescriptionStayNull(): void
    {
        $error = (new ErrorPayloadParser())->parseBody('{"code":"X"}')->first();

        static::assertNotNull($error);
        static::assertNull($error->message());
        static::assertNull($error->description());
    }

    public function testEntriesWithoutACodeAreDropped(): void
    {
        $errors = (new ErrorPayloadParser())->parseBody('[{"message":"no code"},{"code":""},{"code":"  "},{"code":5},{"code":"A"},{"code":null,"message":"x"},{"code":"B"}]');

        static::assertSame(['A', 'B'], $errors->codes());
    }

    public function testASingleObjectWithoutACodeIsEmpty(): void
    {
        static::assertTrue((new ErrorPayloadParser())->parseBody('{"message":"no code"}')->isEmpty());
        static::assertTrue((new ErrorPayloadParser())->parseBody('{"code":""}')->isEmpty());
    }

    public function testAnEmptyBodyOrDocumentIsEmpty(): void
    {
        $parser = new ErrorPayloadParser();

        static::assertTrue($parser->parseBody('')->isEmpty());
        static::assertTrue($parser->parseBody('{}')->isEmpty());
        static::assertTrue($parser->parseBody('[]')->isEmpty());
        static::assertTrue($parser->parseBody('{"errors":[]}')->isEmpty());
        static::assertTrue($parser->parsePayload(Payload::empty())->isEmpty());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideUnreadableBodies(): iterable
    {
        yield 'html' => ['<html>Service Unavailable</html>'];
        yield 'text' => ['Bad Gateway'];
        yield 'truncated json' => ['{"code":'];
        yield 'scalar' => ['"x"'];
        yield 'null' => ['null'];
        yield 'list of scalars' => ['[1,2]'];
        yield 'envelope that is not a list' => ['{"errors":{"a":{}}}'];
        yield 'envelope of scalars' => ['{"errors":["x"]}'];
        yield 'envelope that is a string' => ['{"errors":"x"}'];
    }

    #[DataProvider('provideUnreadableBodies')]
    public function testParseBodyIsLenient(string $body): void
    {
        static::assertTrue((new ErrorPayloadParser())->parseBody($body)->isEmpty());
    }

    public function testParsePayloadIsStrictOnTheStructure(): void
    {
        $parser = new ErrorPayloadParser();

        WireAssertions::assertUnexpected(
            'Unexpected URSSAF response: body is not a JSON array of objects.',
            static fn(): ApiErrors => $parser->parsePayload(JsonDecoder::decode('[1,2]')),
        );
        WireAssertions::assertUnexpected(
            'Unexpected URSSAF response: field "errors" is not a JSON array of objects.',
            static fn(): ApiErrors => $parser->parsePayload(JsonDecoder::decode('{"errors":["x"]}')),
        );
        WireAssertions::assertUnexpected(
            'Unexpected URSSAF response: field "errors" is not a JSON array.',
            static fn(): ApiErrors => $parser->parsePayload(JsonDecoder::decode('{"errors":{"a":{}}}')),
        );
    }

    public function testACodeAtTheTopLevelWinsOverAnEnvelope(): void
    {
        $errors = (new ErrorPayloadParser())->parseBody('{"code":"A","errors":[{"code":"B"}]}');

        static::assertSame(['A'], $errors->codes());
    }

    public function testAMalformedEntryDoesNotHideTheOthersOfALenientBody(): void
    {
        $parser = new ErrorPayloadParser();

        static::assertSame(['ER_API_050_0070'], $parser->parseBody('[{"code":"ER_API_050_0070"},5]')->codes());
        static::assertSame(['A', 'B'], $parser->parseBody('["x",{"code":"A"},null,{"code":"B"}]')->codes());
        static::assertSame(['A'], $parser->parseBody('{"errors":[7,{"code":"A"}]}')->codes());
        WireAssertions::assertUnexpected(
            'Unexpected URSSAF response: body is not a JSON array of objects.',
            static fn(): ApiErrors => $parser->parsePayload(JsonDecoder::decode('[{"code":"A"},5]')),
        );
        WireAssertions::assertUnexpected(
            'Unexpected URSSAF response: field "errors" is not a JSON array of objects.',
            static fn(): ApiErrors => $parser->parsePayload(JsonDecoder::decode('{"errors":[{"code":"A"},5]}')),
        );
    }

    public function testTheRawInputIsSensitiveErrorPayloadParser(): void
    {
        DomainAssertions::assertSensitiveParameters(ErrorPayloadParser::class, 'parseBody', ['body']);
    }
}
