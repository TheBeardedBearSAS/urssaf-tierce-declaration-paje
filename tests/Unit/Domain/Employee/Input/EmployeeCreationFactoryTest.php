<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Employee\Input;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\Input\EmployeeCreationFactory;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Declaration\DeclarationSamples;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\DomainAssertions;

final class EmployeeCreationFactoryTest extends TestCase
{
    public function testFromInput(): void
    {
        $input = [
            'activity_type' => 'A',
            'birth_date' => '1980-01-30',
            'nir' => '280017501234567',
            'licence_start_date' => '2020-05-01',
            'address' => ['municipality_code' => '94022', 'postal_code' => '94000'],
            'identity' => ['birth_name' => 'DUPONT'],
        ];

        $creation = (new EmployeeCreationFactory())->fromInput($input);

        static::assertSame('A', $creation->activityType()?->value());
        static::assertSame('1980-01-30', $creation->birthDate()?->toString());
        static::assertSame('280017501234567', $creation->nir()?->value());
        static::assertSame('2020-05-01', $creation->licenceStartDate()?->toString());
        static::assertSame('94022', $creation->address()?->municipalityCode());
        static::assertSame('DUPONT', $creation->identity()?->birthName()?->value());
        static::assertSame([], DomainAssertions::summary((new EmployeeCreationFactory())->validate($input)));
    }

    public function testEveryFieldIsOptional(): void
    {
        $creation = (new EmployeeCreationFactory())->fromInput([]);

        static::assertTrue($creation->isEmpty());
        static::assertNull($creation->address());
        static::assertNull($creation->identity());
    }

    public function testEveryInvalidFieldIsReportedWithNestedPaths(): void
    {
        $result = (new EmployeeCreationFactory())->validate([
            'activity_type' => 'AG',
            'birth_date' => 'x',
            'nir' => '1',
            'licence_start_date' => '2020-13-01',
            'address' => ['street_number' => 0],
            'identity' => ['birth_name' => 'a', 'birth_city' => str_repeat('A', 31)],
        ]);

        static::assertSame(
            [
                'error activity_type activity_type.too_long',
                'error birth_date calendar_date.invalid_format',
                'error nir nir.too_short',
                'error licence_start_date calendar_date.invalid_date',
                'error address.municipality_code municipality_code.required',
                'error address.postal_code postal_code.required',
                'error address.street_number street_number.out_of_range',
                'error identity.birth_name birth_name.invalid_format',
                'error identity.birth_city birth_city.too_long',
            ],
            DomainAssertions::summary($result),
        );
    }

    public function testASingleInvalidPartIsEnoughToFail(): void
    {
        $cases = [
            'activity_type' => 'AG',
            'birth_date' => 'x',
            'nir' => '1',
            'licence_start_date' => 'x',
            'address' => ['street_number' => 0],
            'identity' => ['birth_name' => 'a'],
        ];
        foreach ($cases as $key => $invalid) {
            static::assertNotSame([], DomainAssertions::summary((new EmployeeCreationFactory())->validate([$key => $invalid])), $key);
        }
        static::assertSame(
            ['error address address.invalid_type'],
            DomainAssertions::summary((new EmployeeCreationFactory())->validate(['address' => 'x'])),
        );
    }

    public function testFromInputThrowsTheViolations(): void
    {
        static::assertSame(
            ['error nir nir.too_short'],
            DeclarationSamples::violations(static fn(): mixed => (new EmployeeCreationFactory())->fromInput(['nir' => '1'])),
        );
    }

    public function testRawInputIsSensitive(): void
    {
        DomainAssertions::assertSensitiveParameters(EmployeeCreationFactory::class, 'validate', ['input']);
        DomainAssertions::assertSensitiveParameters(EmployeeCreationFactory::class, 'fromInput', ['input']);
    }
}
