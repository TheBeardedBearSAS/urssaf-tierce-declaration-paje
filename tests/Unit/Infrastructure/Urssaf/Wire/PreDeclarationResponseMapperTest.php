<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Wire;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Child\ChildCheck;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Child\ChildEntitlementStatus;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\JsonDecoder;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\PreDeclarationResponseMapper;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\Fixtures;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\WireAssertions;

final class PreDeclarationResponseMapperTest extends TestCase
{
    public function testTheOfficialExampleWithValidDatesIsMapped(): void
    {
        $result = PreDeclarationResponseMapper::map(JsonDecoder::decode(Fixtures::load('predeclare_response_valid')));

        static::assertSame('2020-01-01', $result->amounts()?->plannedDebitDate()?->toString());
        static::assertSame('2020-01-31', WireAssertions::present($result->amounts())->plannedPaymentDate()?->toString());
        static::assertSame('0.00', WireAssertions::present($result->amounts())->debitedByPaje()?->toDecimalString());
        static::assertFalse($result->isPajemploiPlus());
        static::assertNull($result->transferDate());
        static::assertCount(1, $result->childChecks());
        static::assertSame(ChildEntitlementStatus::of(1)->raw(), $result->childChecks()[0]->status()->raw());
        static::assertSame('2015-01-12', $result->childChecks()[0]->child()?->birthDate()->toString());
        static::assertCount(1, $result->messages());
        static::assertSame('ER_API_020_NB_0000', $result->messages()[0]->code());
    }

    public function testTheOfficialPlaceholderDatesAreRefused(): void
    {
        WireAssertions::assertUnexpected(
            'Unexpected URSSAF response: field "dtPrelevementPrevisionnelle" is not a Y-m-d date.',
            static fn(): mixed => PreDeclarationResponseMapper::map(JsonDecoder::decode(Fixtures::load('predeclare_response'))),
        );
    }

    public function testAnEmptyResultHasNoValue(): void
    {
        foreach ([JsonDecoder::decode('{}'), JsonDecoder::decode('{"declaration":null,"indcDeclPajemploiPlus":null,"dateVirement":null}')] as $payload) {
            $result = PreDeclarationResponseMapper::map($payload);

            static::assertNull($result->amounts());
            static::assertFalse($result->isPajemploiPlus());
            static::assertNull($result->transferDate());
            static::assertSame([], $result->childChecks());
            static::assertSame([], $result->messages());
        }
    }

    public function testAnEmptyDeclarationObjectGivesEmptyAmounts(): void
    {
        $result = PreDeclarationResponseMapper::map(JsonDecoder::decode('{"declaration":{}}'));

        static::assertNull(WireAssertions::present($result->amounts())->contributions());
    }

    public function testThePajemploiPlusIndicatorAndTheTransferDateAreRead(): void
    {
        $result = PreDeclarationResponseMapper::map(JsonDecoder::decode('{"indcDeclPajemploiPlus":true,"dateVirement":"2026-11-12"}'));

        static::assertTrue($result->isPajemploiPlus());
        static::assertSame('2026-11-12', $result->transferDate()?->toString());

        static::assertFalse(PreDeclarationResponseMapper::map(JsonDecoder::decode('{"indcDeclPajemploiPlus":false}'))->isPajemploiPlus());
    }

    public function testAMalformedTransferDateOrIndicatorIsRefused(): void
    {
        WireAssertions::assertUnexpected(
            'Unexpected URSSAF response: field "dateVirement" is not a Y-m-d date.',
            static fn(): mixed => PreDeclarationResponseMapper::map(JsonDecoder::decode('{"dateVirement":"string"}')),
        );
        WireAssertions::assertUnexpected(
            'Unexpected URSSAF response: field "indcDeclPajemploiPlus" is not a boolean.',
            static fn(): mixed => PreDeclarationResponseMapper::map(JsonDecoder::decode('{"indcDeclPajemploiPlus":"true"}')),
        );
        WireAssertions::assertUnexpected(
            'Unexpected URSSAF response: field "declaration" is not an object.',
            static fn(): mixed => PreDeclarationResponseMapper::map(JsonDecoder::decode('{"declaration":12}')),
        );
    }

    public function testAnEntryWithoutStatusIsSkippedAndTheOthersKeepTheirOrder(): void
    {
        $result = PreDeclarationResponseMapper::map(JsonDecoder::decode(
            '{"verificationEnfantPe":[{"reponseStatutEnfant":2},{"inputEnfantPe":{"dtNaissanceEnfant":"2015-01-12",'
            . '"nomEnfant":"A","prenomEnfant":"B"}},{"reponseStatutEnfant":0}]}',
        ));

        static::assertSame(
            [2, 0],
            array_map(static fn(ChildCheck $check): int => $check->status()->raw(), $result->childChecks()),
        );
        static::assertNull($result->childChecks()[0]->child());
    }

    public function testAMalformedChildCheckIsRefused(): void
    {
        WireAssertions::assertUnexpected(
            'Unexpected URSSAF response: field "reponseStatutEnfant" is not an integer.',
            static fn(): mixed => PreDeclarationResponseMapper::map(JsonDecoder::decode('{"verificationEnfantPe":[{"reponseStatutEnfant":"1"}]}')),
        );
        WireAssertions::assertUnexpected(
            'Unexpected URSSAF response: field "verificationEnfantPe" is not a JSON array.',
            static fn(): mixed => PreDeclarationResponseMapper::map(JsonDecoder::decode('{"verificationEnfantPe":"x"}')),
        );
    }

    public function testMessagesNeverFailTheResult(): void
    {
        $result = PreDeclarationResponseMapper::map(JsonDecoder::decode('{"erreursNonBloquantes":[{"code":"NEW_CODE"}]}'));

        static::assertSame('NEW_CODE', $result->messages()[0]->code());
    }

    public function testWhatIsNotAJsonObjectIsRefused(): void
    {
        WireAssertions::assertRefusesWhatIsNotAnObject(PreDeclarationResponseMapper::map(...));
    }
}
