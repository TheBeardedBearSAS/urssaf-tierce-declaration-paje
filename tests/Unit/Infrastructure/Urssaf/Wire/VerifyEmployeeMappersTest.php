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
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\Nir;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\PajeEmployeeNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\JsonDecoder;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\JsonEncoder;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\VerifyEmployeeRequestMapper;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\VerifyEmployeeResponseMapper;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\EmployeeWireSamples;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\Fixtures;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\WireAssertions;

final class VerifyEmployeeMappersTest extends TestCase
{
    public function testTheOfficialCallHasAnEmptyObjectBody(): void
    {
        $identification = EmployeeByNir::of(Nir::of('178010701005265'), CalendarDate::fromString('1979-01-01'));

        static::assertSame(
            ['nirSalarie' => '178010701005265', 'dtNaissanceSalarie' => '1979-01-01'],
            VerifyEmployeeRequestMapper::query($identification),
        );
        static::assertSame(trim(Fixtures::load('verify_employee_request')), JsonEncoder::encode(VerifyEmployeeRequestMapper::body($identification)));
    }

    public function testTheBodyIsAnEmptyObjectForANumber(): void
    {
        static::assertSame(
            '{}',
            JsonEncoder::encode(VerifyEmployeeRequestMapper::body(EmployeeByNumber::of(PajeEmployeeNumber::of('Y1869005780009')))),
        );
    }

    public function testAdministrativeDataIsTheBody(): void
    {
        $identification = EmployeeByAdministrativeData::of(EmployeeWireSamples::identity(), CalendarDate::fromString('1980-01-30'));
        $expected = Fixtures::section('associate_employee_request_valid', 'inputIdentificationSalarie');

        static::assertSame($expected, VerifyEmployeeRequestMapper::body($identification)->members());
        static::assertSame(['dtNaissanceSalarie' => '1980-01-30'], VerifyEmployeeRequestMapper::query($identification));
    }

    public function testTheOfficialResponseIsMapped(): void
    {
        $account = VerifyEmployeeResponseMapper::map(JsonDecoder::decode(Fixtures::load('verify_employee_response')));

        static::assertSame('string', $account->employeeNumber());
        static::assertTrue($account->childminder());
        static::assertTrue($account->homeCare());
        static::assertSame('Actif', $account->state()->raw());
    }

    public function testAMalformedResponseIsRefused(): void
    {
        WireAssertions::assertUnexpected(
            'Unexpected URSSAF response: field "indcAma" is not a boolean.',
            static fn(): mixed => VerifyEmployeeResponseMapper::map(JsonDecoder::decode('{"indcAma":"x"}')),
        );
    }

    public function testWhatIsNotAJsonObjectIsRefused(): void
    {
        WireAssertions::assertRefusesWhatIsNotAnObject(VerifyEmployeeResponseMapper::map(...));
    }
}
