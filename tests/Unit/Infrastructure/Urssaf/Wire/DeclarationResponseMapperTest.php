<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Wire;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\DeclarationResponseMapper;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\JsonDecoder;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\Fixtures;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\WireAssertions;

final class DeclarationResponseMapperTest extends TestCase
{
    public function testTheOfficialExampleWithValidDatesIsMapped(): void
    {
        $result = DeclarationResponseMapper::map(JsonDecoder::decode(Fixtures::load('declare_response_valid')));

        static::assertSame('string', $result->documentReference());
        static::assertSame('2020-01-01', $result->amounts()?->plannedDebitDate()?->toString());
        static::assertSame('2020-01-31', WireAssertions::present($result->amounts())->plannedPaymentDate()?->toString());
        static::assertSame('0.00', WireAssertions::present($result->amounts())->payableToEmployee()?->toDecimalString());
        static::assertFalse($result->isPajemploiPlus());
        static::assertNull($result->transferDate());
        static::assertCount(1, $result->childChecks());
        static::assertSame(1, $result->childChecks()[0]->status()->raw());
        static::assertCount(1, $result->messages());
        static::assertSame('ER_API_020_NB_0000', $result->messages()[0]->code());
    }

    public function testTheOfficialPlaceholderDatesAreRefused(): void
    {
        WireAssertions::assertUnexpected(
            'Unexpected URSSAF response: field "dtPrelevementPrevisionnelle" is not a Y-m-d date.',
            static fn(): mixed => DeclarationResponseMapper::map(JsonDecoder::decode(Fixtures::load('declare_response'))),
        );
    }

    public function testEveryFieldIsCarriedOver(): void
    {
        $result = DeclarationResponseMapper::map(JsonDecoder::decode(
            '{"referenceDocumentaire":"REF-1","declaration":{"mntCotisations":12.5,"txPas":4.3},'
            . '"indcDeclPajemploiPlus":true,"dateVirement":"2026-11-12",'
            . '"verificationEnfantPe":[{"reponseStatutEnfant":1}],"erreursNonBloquantes":[{"code":"X"}]}',
        ));

        static::assertSame('REF-1', $result->documentReference());
        static::assertSame('12.50', $result->amounts()?->contributions()?->toDecimalString());
        static::assertSame('4.3', WireAssertions::present($result->amounts())->withholding()->rate()?->toString());
        static::assertTrue($result->isPajemploiPlus());
        static::assertSame('2026-11-12', $result->transferDate()?->toString());
        static::assertCount(1, $result->childChecks());
        static::assertSame('X', $result->messages()[0]->code());
    }

    public function testAMalformedNonBlockingListNeverFailsTheMapping(): void
    {
        $result = DeclarationResponseMapper::map(JsonDecoder::decode(
            '{"referenceDocumentaire":"REF-1","erreursNonBloquantes":{"code":"X"}}',
        ));
        static::assertSame('REF-1', $result->documentReference());
        static::assertSame([], $result->messages());

        $result = DeclarationResponseMapper::map(JsonDecoder::decode('{"referenceDocumentaire":"REF-1","erreursNonBloquantes":["x"]}'));
        static::assertSame([], $result->messages());
    }

    public function testAnEmptyAnswerIsARecordedDeclarationWithoutDetail(): void
    {
        foreach ([JsonDecoder::decode('{}'), JsonDecoder::decode('{"referenceDocumentaire":null}')] as $payload) {
            $result = DeclarationResponseMapper::map($payload);

            static::assertNull($result->documentReference());
            static::assertNull($result->amounts());
            static::assertFalse($result->isPajemploiPlus());
            static::assertNull($result->transferDate());
            static::assertSame([], $result->childChecks());
            static::assertSame([], $result->messages());
        }
    }

    public function testAnEmptyReferenceIsKept(): void
    {
        static::assertSame('', DeclarationResponseMapper::map(JsonDecoder::decode('{"referenceDocumentaire":""}'))->documentReference());
    }

    public function testAMalformedReferenceIsRefused(): void
    {
        WireAssertions::assertUnexpected(
            'Unexpected URSSAF response: field "referenceDocumentaire" is not a string.',
            static fn(): mixed => DeclarationResponseMapper::map(JsonDecoder::decode('{"referenceDocumentaire":42}')),
        );
    }

    public function testWhatIsNotAJsonObjectIsRefused(): void
    {
        WireAssertions::assertRefusesWhatIsNotAnObject(DeclarationResponseMapper::map(...));
    }
}
