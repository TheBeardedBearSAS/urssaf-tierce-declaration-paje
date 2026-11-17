<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Employer\Input;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\Input\EmployerReferenceFactory;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Declaration\DeclarationSamples;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\DomainAssertions;

final class EmployerReferenceFactoryTest extends TestCase
{
    public function testFromInput(): void
    {
        $employer = (new EmployerReferenceFactory())->fromInput(['employer_number' => 'Y1869005780009', 'birth_date' => '1975-02-23']);

        static::assertSame('Y1869005780009', $employer->number()->value());
        static::assertTrue($employer->birthDate()->equals(DeclarationSamples::date('1975-02-23')));
        static::assertSame([], DomainAssertions::summary((new EmployerReferenceFactory())->validate(['employer_number' => 'Y1869005780009', 'birth_date' => '1975-02-23'])));
    }

    public function testEveryMissingFieldIsReported(): void
    {
        static::assertSame(
            ['error employer_number employer_number.required', 'error birth_date birth_date.required'],
            DomainAssertions::summary((new EmployerReferenceFactory())->validate([])),
        );
    }

    public function testEveryInvalidFieldIsReported(): void
    {
        static::assertSame(
            ['error employer_number employer_number.too_short', 'error birth_date calendar_date.invalid_date'],
            DomainAssertions::summary((new EmployerReferenceFactory())->validate(['employer_number' => '123', 'birth_date' => '1975-02-30'])),
        );
    }

    public function testASingleInvalidPartIsEnoughToFail(): void
    {
        $factory = new EmployerReferenceFactory();

        static::assertSame(
            ['error birth_date calendar_date.invalid_format'],
            DomainAssertions::summary($factory->validate(['employer_number' => 'Y1869005780009', 'birth_date' => 'x'])),
        );
        static::assertSame(
            ['error employer_number employer_number.too_long'],
            DomainAssertions::summary($factory->validate(['employer_number' => 'Y18690057800091', 'birth_date' => '1975-02-23'])),
        );
    }

    public function testFromInputThrowsEveryViolation(): void
    {
        $violations = DeclarationSamples::violations(static fn(): mixed => (new EmployerReferenceFactory())->fromInput(['employer_number' => 12, 'birth_date' => []]));

        static::assertSame(['error employer_number employer_number.too_short', 'error birth_date birth_date.invalid_type'], $violations);
    }

    public function testRawInputIsSensitive(): void
    {
        DomainAssertions::assertSensitiveParameters(EmployerReferenceFactory::class, 'validate', ['input']);
        DomainAssertions::assertSensitiveParameters(EmployerReferenceFactory::class, 'fromInput', ['input']);
    }

    public function testNoRawValueAppearsInTheViolations(): void
    {
        $result = (new EmployerReferenceFactory())->validate(['employer_number' => 'SECRET', 'birth_date' => 'SECRET-DATE']);

        foreach ($result->all() as $violation) {
            static::assertStringNotContainsString('SECRET', $violation->message());
        }
    }
}
