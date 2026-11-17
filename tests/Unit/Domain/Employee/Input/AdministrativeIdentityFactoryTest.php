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
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\Input\AdministrativeIdentityFactory;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Declaration\DeclarationSamples;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\DomainAssertions;

final class AdministrativeIdentityFactoryTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private static function valid(): array
    {
        return [
            'civility' => '1',
            'birth_name' => 'DUPONT',
            'usage_name' => 'MARTIN',
            'first_name' => 'JEAN-PAUL',
            'birth_date' => '1980-01-30',
            'birth_country_code' => '100',
            'birth_department' => '07',
            'birth_city' => 'Saint-Étienne',
        ];
    }

    public function testFromInput(): void
    {
        $identity = (new AdministrativeIdentityFactory())->fromInput(self::valid());

        static::assertSame('1', $identity->civility()?->value());
        static::assertSame('DUPONT', $identity->birthName()?->value());
        static::assertSame('MARTIN', $identity->usageName()?->value());
        static::assertSame('JEAN-PAUL', $identity->firstName()?->value());
        static::assertSame('1980-01-30', $identity->birthDate()?->toString());
        static::assertSame('100', $identity->birthCountryCode());
        static::assertSame('07', $identity->birthDepartment());
        static::assertSame('Saint-Étienne', $identity->birthCity());
        static::assertSame([], DomainAssertions::summary((new AdministrativeIdentityFactory())->validate(self::valid())));
    }

    public function testEveryFieldIsOptional(): void
    {
        static::assertTrue((new AdministrativeIdentityFactory())->fromInput([])->isEmpty());
        static::assertTrue((new AdministrativeIdentityFactory())->fromInput(['birth_name' => '', 'birth_city' => ''])->isEmpty());
    }

    public function testEveryInvalidFieldIsReported(): void
    {
        $result = (new AdministrativeIdentityFactory())->validate([
            'civility' => '12',
            'birth_name' => 'dupont',
            'usage_name' => 'martin',
            'first_name' => 'jean',
            'birth_date' => '1980-02-31',
            'birth_country_code' => '1000',
            'birth_department' => '0700',
            'birth_city' => str_repeat('A', 31),
        ]);

        static::assertSame(
            [
                'error civility civility.too_long',
                'error birth_name birth_name.invalid_format',
                'error usage_name usage_name.invalid_format',
                'error first_name first_name.invalid_format',
                'error birth_date calendar_date.invalid_date',
                'error birth_country_code birth_country_code.too_long',
                'error birth_department birth_department.too_long',
                'error birth_city birth_city.too_long',
            ],
            DomainAssertions::summary($result),
        );
    }

    public function testASingleInvalidPartIsEnoughToFail(): void
    {
        foreach (['civility' => '12', 'birth_name' => 'a', 'usage_name' => 'a', 'first_name' => 'a', 'birth_date' => 'x', 'birth_country_code' => '1000', 'birth_department' => '1000', 'birth_city' => '1_'] as $key => $invalid) {
            $result = (new AdministrativeIdentityFactory())->validate([$key => $invalid] + self::valid());
            static::assertCount(1, $result->errors(), $key);
            static::assertSame($key, $result->errors()[0]->path());
        }
    }

    public function testFromInputThrowsTheViolations(): void
    {
        static::assertSame(
            ['error birth_name birth_name.invalid_type'],
            DeclarationSamples::violations(static fn(): mixed => (new AdministrativeIdentityFactory())->fromInput(['birth_name' => 1.5])),
        );
    }

    public function testRawInputIsSensitive(): void
    {
        DomainAssertions::assertSensitiveParameters(AdministrativeIdentityFactory::class, 'validate', ['input']);
        DomainAssertions::assertSensitiveParameters(AdministrativeIdentityFactory::class, 'fromInput', ['input']);
    }
}
