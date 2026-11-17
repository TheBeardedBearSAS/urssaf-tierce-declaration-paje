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

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\OnlineAccountStateValue;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\JsonDecoder;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\QueryEncoding;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\VerifyEmployerRequestMapper;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\VerifyEmployerResponseMapper;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\EmployeeWireSamples;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\Fixtures;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\WireAssertions;

final class VerifyEmployerMappersTest extends TestCase
{
    public function testTheRequestIsTheOfficialQuery(): void
    {
        static::assertSame(
            'dtNaissanceEmployeur=1975-02-23',
            QueryEncoding::build(VerifyEmployerRequestMapper::query(EmployeeWireSamples::employer())),
        );
    }

    public function testTheOfficialResponseIsMapped(): void
    {
        $result = VerifyEmployerResponseMapper::map(JsonDecoder::decode(Fixtures::load('verify_employer_response')));

        static::assertTrue($result->authorised());
        static::assertSame(OnlineAccountStateValue::Active, $result->state()->documented());
    }

    public function testAFalseAnswerIsKept(): void
    {
        $result = VerifyEmployerResponseMapper::map(JsonDecoder::decode(
            '{"employeurAutoriseOk":false,"etatCompteEnLigne":"Coordonnées bancaires invalides"}',
        ));

        static::assertFalse($result->authorised());
        static::assertSame(OnlineAccountStateValue::InvalidBankDetails, $result->state()->documented());
    }

    public function testAbsentFieldsAreNotAuthorisedAndAnEmptyState(): void
    {
        foreach ([JsonDecoder::decode('{}'), JsonDecoder::decode('{"employeurAutoriseOk":null,"etatCompteEnLigne":null}')] as $payload) {
            $result = VerifyEmployerResponseMapper::map($payload);

            static::assertFalse($result->authorised());
            static::assertSame('', $result->state()->raw());
        }
    }

    public function testAnUndocumentedStateIsKeptRaw(): void
    {
        $result = VerifyEmployerResponseMapper::map(JsonDecoder::decode('{"etatCompteEnLigne":"Autre"}'));

        static::assertSame('Autre', $result->state()->raw());
        static::assertNull($result->state()->documented());
    }

    public function testAFieldOfTheWrongTypeIsRefused(): void
    {
        WireAssertions::assertUnexpected(
            'Unexpected URSSAF response: field "employeurAutoriseOk" is not a boolean.',
            static fn(): mixed => VerifyEmployerResponseMapper::map(JsonDecoder::decode('{"employeurAutoriseOk":"oui"}')),
        );
        WireAssertions::assertUnexpected(
            'Unexpected URSSAF response: field "etatCompteEnLigne" is not a string.',
            static fn(): mixed => VerifyEmployerResponseMapper::map(JsonDecoder::decode('{"etatCompteEnLigne":1}')),
        );
    }

    public function testWhatIsNotAJsonObjectIsRefused(): void
    {
        WireAssertions::assertRefusesWhatIsNotAnObject(VerifyEmployerResponseMapper::map(...));
    }
}
