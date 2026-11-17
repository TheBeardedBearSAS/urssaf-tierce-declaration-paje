<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Wire;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Message\NonBlockingCode;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Message\NonBlockingMessage;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\JsonDecoder;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\NonBlockingMessagesMapper;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\Payload;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\Fixtures;

final class NonBlockingMessagesMapperTest extends TestCase
{
    public function testTheOfficialExampleIsMapped(): void
    {
        $messages = NonBlockingMessagesMapper::map(JsonDecoder::decode(Fixtures::load('predeclare_response')));

        static::assertCount(1, $messages);
        static::assertSame('ER_API_020_NB_0000', $messages[0]->code());
        static::assertSame(NonBlockingCode::EmployerNotAuthorised, $messages[0]->knownCode());
        static::assertSame("L'inscription au site internet n'est pas effective", $messages[0]->message());
        static::assertSame("Le compte employeur n'est pas actif dans le SI Pajemploi.", $messages[0]->description());
    }

    public function testEveryOfficialResultFixtureWithMessagesIsMapped(): void
    {
        foreach (['associate_employee_response', 'declare_response', 'estimate_response', 'verify_children_response', 'verify_employee_response', 'verify_employer_response'] as $name) {
            $messages = NonBlockingMessagesMapper::map(JsonDecoder::decode(Fixtures::load($name)));

            foreach ($messages as $message) {
                static::assertNotSame('', $message->code(), $name);
            }
        }
    }

    public function testAnAbsentListIsEmpty(): void
    {
        static::assertSame([], NonBlockingMessagesMapper::map(Payload::empty()));
        static::assertSame([], NonBlockingMessagesMapper::map(JsonDecoder::decode('{"declaration":{}}')));
        static::assertSame([], NonBlockingMessagesMapper::map(JsonDecoder::decode('{"erreursNonBloquantes":null}')));
        static::assertSame([], NonBlockingMessagesMapper::map(JsonDecoder::decode('{"erreursNonBloquantes":[]}')));
    }

    public function testMessagesKeepTheirOrderAndUnknownCodesAreKept(): void
    {
        $messages = NonBlockingMessagesMapper::map(JsonDecoder::decode(
            '{"erreursNonBloquantes":[{"code":"ER_AG_NB_00010 ","message":"m1","description":"d1"},{"code":"NEW_CODE","message":"m2"}]}',
        ));

        static::assertSame(['ER_AG_NB_00010', 'NEW_CODE'], array_map(static fn(NonBlockingMessage $m): string => $m->code(), $messages));
        static::assertSame(NonBlockingCode::AgreementExpiring, $messages[0]->knownCode());
        static::assertNull($messages[1]->knownCode());
        static::assertSame('m2', $messages[1]->message());
        static::assertNull($messages[1]->description());
    }

    public function testAnEntryWithoutCodeKeepsAnEmptyCodeAndOddFieldsReadAsAbsent(): void
    {
        $messages = NonBlockingMessagesMapper::map(JsonDecoder::decode(
            '{"erreursNonBloquantes":[{"message":"m"},{"code":12,"message":3,"description":false}]}',
        ));

        static::assertCount(2, $messages);
        static::assertSame('', $messages[0]->code());
        static::assertSame('m', $messages[0]->message());
        static::assertSame('', $messages[1]->code());
        static::assertNull($messages[1]->message());
        static::assertNull($messages[1]->description());
    }

    public function testAMalformedListIsReadLeniently(): void
    {
        foreach (['{"code":"x"}', '"x"', '12', 'true', '["x"]', '[1,null,"y"]'] as $list) {
            static::assertSame([], NonBlockingMessagesMapper::map(JsonDecoder::decode('{"erreursNonBloquantes":' . $list . '}')), $list);
        }

        $messages = NonBlockingMessagesMapper::map(JsonDecoder::decode('{"erreursNonBloquantes":["x",{"code":"A","message":"m"},3]}'));

        static::assertCount(1, $messages);
        static::assertSame('A', $messages[0]->code());
    }

    public function testACodeBrokenAcrossLinesIsNormalised(): void
    {
        $messages = NonBlockingMessagesMapper::map(JsonDecoder::decode(
            '{"erreursNonBloquantes":[{"code":" ER_API_023_\\n NB_0000 ","message":"m"}]}',
        ));

        static::assertSame('ER_API_023_NB_0000', $messages[0]->code());
        static::assertSame(NonBlockingCode::EmployeeAlreadyAssociated, $messages[0]->knownCode());
    }
}
