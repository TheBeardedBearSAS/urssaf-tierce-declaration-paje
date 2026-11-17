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
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeByAdministrativeData;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeByNir;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeByNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeCreation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\Nir;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\PajeEmployeeNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Message\NonBlockingCode;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\AssociateEmployeeRequestMapper;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\AssociateEmployeeResponseMapper;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\JsonDecoder;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\JsonEncoder;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\QueryEncoding;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\EmployeeWireSamples;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\Fixtures;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\WireAssertions;

final class AssociateEmployeeMappersTest extends TestCase
{
    public function testTheQueryStartsWithTheEmployerThenTheIdentification(): void
    {
        $query = AssociateEmployeeRequestMapper::query(
            EmployeeWireSamples::employer(),
            EmployeeByNumber::of(PajeEmployeeNumber::of('Y1869005780009'), CalendarDate::fromString('1980-01-30')),
        );

        static::assertSame(
            ['dtNaissanceEmployeur' => '1975-02-23', 'numeroPajeSalarie' => 'Y1869005780009', 'dtNaissanceSalarie' => '1980-01-30'],
            $query,
        );
        static::assertSame(
            'dtNaissanceEmployeur=1975-02-23&numeroPajeSalarie=Y1869005780009&dtNaissanceSalarie=1980-01-30',
            QueryEncoding::build($query),
        );
    }

    public function testTheQueryCarriesTheNir(): void
    {
        static::assertSame(
            ['dtNaissanceEmployeur' => '1975-02-23', 'nirSalarie' => '178010701005265'],
            AssociateEmployeeRequestMapper::query(EmployeeWireSamples::employer(), EmployeeByNir::of(Nir::of('178010701005265'))),
        );
    }

    public function testAdministrativeDataContributesNoCriterionAndItsIdentityIsNotSent(): void
    {
        $identification = EmployeeByAdministrativeData::of(EmployeeWireSamples::identity());

        static::assertSame(
            ['dtNaissanceEmployeur' => '1975-02-23'],
            AssociateEmployeeRequestMapper::query(EmployeeWireSamples::employer(), $identification),
        );
        // The body comes from the creation only.
        static::assertSame(
            '{}',
            JsonEncoder::encode(AssociateEmployeeRequestMapper::body(EmployeeCreation::of(null, null, null, null, null, null))),
        );
    }

    public function testTheBodyIsTheOfficialCreation(): void
    {
        $official = Fixtures::json('associate_employee_request_valid');
        unset($official['numeroPajeSalarie']);

        static::assertSame(
            json_encode($official, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            JsonEncoder::encode(AssociateEmployeeRequestMapper::body(EmployeeWireSamples::creation())),
        );
    }

    public function testTheOfficialResponseIsMapped(): void
    {
        $result = AssociateEmployeeResponseMapper::map(JsonDecoder::decode(Fixtures::load('associate_employee_response')));

        static::assertTrue($result->created());
        static::assertTrue($result->associated());
        static::assertFalse($result->alreadyAssociated());
        $account = WireAssertions::present($result->account());
        static::assertSame('Actif', $account->state()->raw());
        static::assertTrue($account->childminder());
        static::assertCount(1, $result->messages());
        static::assertSame(NonBlockingCode::EmployerNotAuthorised, $result->messages()[0]->knownCode());
    }

    public function testEachFlagIsReadFromItsOwnField(): void
    {
        $already = AssociateEmployeeResponseMapper::map(JsonDecoder::decode('{"salarieDejaAssocieOK":true}'));
        $associated = AssociateEmployeeResponseMapper::map(JsonDecoder::decode('{"salarieAssocieOk":true}'));
        $created = AssociateEmployeeResponseMapper::map(JsonDecoder::decode('{"salarieCreeOk":true}'));

        static::assertSame([false, false, true], [$already->created(), $already->associated(), $already->alreadyAssociated()]);
        static::assertSame([false, true, false], [$associated->created(), $associated->associated(), $associated->alreadyAssociated()]);
        static::assertSame([true, false, false], [$created->created(), $created->associated(), $created->alreadyAssociated()]);
    }

    public function testAbsentFieldsAreFalseNullAndEmpty(): void
    {
        foreach ([JsonDecoder::decode('{}'), JsonDecoder::decode('{"salarieCreeOk":null,"salarieAssocieOk":null,"salarieDejaAssocieOK":null,"outputVerificationSp":null,"erreursNonBloquantes":null}')] as $payload) {
            $result = AssociateEmployeeResponseMapper::map($payload);

            static::assertFalse($result->created());
            static::assertFalse($result->associated());
            static::assertFalse($result->alreadyAssociated());
            static::assertNull($result->account());
            static::assertSame([], $result->messages());
        }
    }

    public function testAMalformedResponseIsRefused(): void
    {
        foreach ([
            '{"salarieCreeOk":"x"}' => 'field "salarieCreeOk" is not a boolean',
            '{"salarieAssocieOk":1}' => 'field "salarieAssocieOk" is not a boolean',
            '{"salarieDejaAssocieOK":"x"}' => 'field "salarieDejaAssocieOK" is not a boolean',
            '{"outputVerificationSp":"x"}' => 'field "outputVerificationSp" is not an object',
            '{"outputVerificationSp":{"indcGed":"x"}}' => 'field "indcGed" is not a boolean',
        ] as $json => $reason) {
            WireAssertions::assertUnexpected(
                'Unexpected URSSAF response: ' . $reason . '.',
                static fn(): mixed => AssociateEmployeeResponseMapper::map(JsonDecoder::decode($json)),
            );
        }
    }

    public function testAMalformedMessageListIsReadLeniently(): void
    {
        static::assertSame([], AssociateEmployeeResponseMapper::map(JsonDecoder::decode('{"erreursNonBloquantes":"x"}'))->messages());
    }

    public function testWhatIsNotAJsonObjectIsRefused(): void
    {
        WireAssertions::assertRefusesWhatIsNotAnObject(AssociateEmployeeResponseMapper::map(...));
    }
}
