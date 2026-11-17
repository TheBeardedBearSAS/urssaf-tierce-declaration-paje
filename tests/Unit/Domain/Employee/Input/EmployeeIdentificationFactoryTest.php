<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Employee\Input;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeByAdministrativeData;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeByNir;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeByNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeIdentificationKind;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\Input\EmployeeIdentificationFactory;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Declaration\DeclarationSamples;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\DomainAssertions;

/**
 * @mago-expect lint:too-many-methods
 */
final class EmployeeIdentificationFactoryTest extends TestCase
{
    public function testByNumber(): void
    {
        $employee = (new EmployeeIdentificationFactory())->fromInput(['employee_number' => 'Y2926822480004', 'birth_date' => '1990-04-05']);

        static::assertInstanceOf(EmployeeByNumber::class, $employee);
        static::assertSame(EmployeeIdentificationKind::Number, $employee->kind());
        static::assertSame('1990-04-05', $employee->birthDate()?->toString());
    }

    public function testByNir(): void
    {
        $employee = (new EmployeeIdentificationFactory())->fromInput(['nir' => '280017501234567']);

        static::assertInstanceOf(EmployeeByNir::class, $employee);
    }

    public function testByAdministrativeData(): void
    {
        $employee = (new EmployeeIdentificationFactory())->fromInput([
            'identity' => ['birth_name' => 'DUPONT', 'first_name' => 'JEAN'],
            'birth_date' => '1990-04-05',
        ]);

        static::assertInstanceOf(EmployeeByAdministrativeData::class, $employee);
        static::assertSame('DUPONT', $employee->identity()->birthName()?->value());
        static::assertSame('1990-04-05', $employee->birthDate()?->toString());
    }

    public function testThePriorityIsNumberThenNirThenIdentity(): void
    {
        $all = ['employee_number' => 'Y2926822480004', 'nir' => '280017501234567', 'identity' => ['birth_name' => 'DUPONT']];

        static::assertInstanceOf(EmployeeByNumber::class, (new EmployeeIdentificationFactory())->fromInput($all));
        static::assertInstanceOf(EmployeeByNir::class, (new EmployeeIdentificationFactory())->fromInput(['employee_number' => ''] + $all));
        static::assertInstanceOf(EmployeeByAdministrativeData::class, (new EmployeeIdentificationFactory())->fromInput(['employee_number' => null, 'nir' => ''] + $all));
    }

    public function testAnEmptyIdentityIsNotAnIdentificationCriterion(): void
    {
        $factory = new EmployeeIdentificationFactory();

        static::assertSame(['error identity employee_identification.empty'], DomainAssertions::summary($factory->validate([])));
        static::assertSame(['error identity employee_identification.empty'], DomainAssertions::summary($factory->validate(['birth_date' => '1990-04-05'])));
        static::assertSame(['error identity employee_identification.empty'], DomainAssertions::summary($factory->validate(['identity' => []])));
        static::assertSame(['error identity employee_identification.empty'], DomainAssertions::summary($factory->validate(['identity' => ['birth_name' => '', 'birth_city' => '']])));
    }

    public function testAnInvalidPartDoesNotHideAMissingCriterion(): void
    {
        $factory = new EmployeeIdentificationFactory();

        static::assertSame(
            ['error birth_date calendar_date.invalid_format', 'error identity employee_identification.empty'],
            DomainAssertions::summary($factory->validate(['birth_date' => 'x'])),
        );
        static::assertSame(
            ['error identity identity.invalid_type'],
            DomainAssertions::summary($factory->validate(['identity' => 'x'])),
        );
        static::assertSame(
            ['error identity.birth_name birth_name.invalid_format'],
            DomainAssertions::summary($factory->validate(['identity' => ['birth_name' => 'a']])),
        );
        static::assertSame(
            ['error employee_number employee_number.too_short'],
            DomainAssertions::summary($factory->validate(['employee_number' => '1', 'identity' => ['birth_name' => 'A']])),
        );
    }

    public function testAnEmptyIdentityIsAcceptedBesideAHigherCriterion(): void
    {
        static::assertInstanceOf(
            EmployeeByNumber::class,
            (new EmployeeIdentificationFactory())->fromInput(['employee_number' => 'Y2926822480004', 'identity' => []]),
        );
    }

    public function testEveryGivenCriterionIsValidated(): void
    {
        $result = (new EmployeeIdentificationFactory())->validate([
            'employee_number' => '1',
            'nir' => '2',
            'birth_date' => 'x',
            'identity' => ['birth_name' => 'a1', 'birth_city' => str_repeat('A', 31)],
        ]);

        static::assertSame(
            [
                'error identity.birth_name birth_name.invalid_format',
                'error identity.birth_city birth_city.too_long',
                'error birth_date calendar_date.invalid_format',
                'error employee_number employee_number.too_short',
                'error nir nir.too_short',
            ],
            DomainAssertions::summary($result),
        );
    }

    public function testASingleInvalidPartIsEnoughToFail(): void
    {
        $factory = new EmployeeIdentificationFactory();

        static::assertSame(
            ['error identity.birth_name birth_name.invalid_format'],
            DomainAssertions::summary($factory->validate(['employee_number' => 'Y2926822480004', 'identity' => ['birth_name' => 'a']])),
        );
        static::assertSame(
            ['error birth_date calendar_date.invalid_format'],
            DomainAssertions::summary($factory->validate(['identity' => ['birth_name' => 'A'], 'birth_date' => 'x'])),
        );
        static::assertSame(
            ['error identity identity.invalid_type'],
            DomainAssertions::summary($factory->validate(['employee_number' => 'Y2926822480004', 'identity' => 'x'])),
        );
        static::assertSame(
            ['error identity.birth_city birth_city.too_long'],
            DomainAssertions::summary($factory->validate(['identity' => ['birth_city' => str_repeat('A', 31)]])),
        );
    }

    public function testFromInputThrowsTheViolations(): void
    {
        static::assertSame(
            ['error identity employee_identification.empty'],
            DeclarationSamples::violations(static fn(): mixed => (new EmployeeIdentificationFactory())->fromInput([])),
        );
    }

    public function testRawInputIsSensitive(): void
    {
        DomainAssertions::assertSensitiveParameters(EmployeeIdentificationFactory::class, 'validate', ['input']);
        DomainAssertions::assertSensitiveParameters(EmployeeIdentificationFactory::class, 'fromInput', ['input']);
    }
}
