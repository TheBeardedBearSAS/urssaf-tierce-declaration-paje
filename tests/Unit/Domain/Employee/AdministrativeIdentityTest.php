<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Employee;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\AdministrativeIdentity;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\Civility;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\UpperCaseName;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\DomainAssertions;

final class AdministrativeIdentityTest extends TestCase
{
    public function testOfKeepsEveryField(): void
    {
        $civility = Civility::mister();
        $birthName = UpperCaseName::of('DUPONT', 'birth_name');
        $usageName = UpperCaseName::of('MARTIN', 'usage_name');
        $firstName = UpperCaseName::of('JEAN-MICHEL', 'first_name');
        $birthDate = CalendarDate::fromString('1980-01-30');

        $identity = AdministrativeIdentity::of($civility, $birthName, $usageName, $firstName, $birthDate, '100', '972', "L'HAY-LES-ROSES");

        static::assertSame($civility, $identity->civility());
        static::assertSame($birthName, $identity->birthName());
        static::assertSame($usageName, $identity->usageName());
        static::assertSame($firstName, $identity->firstName());
        static::assertSame($birthDate, $identity->birthDate());
        static::assertSame('100', $identity->birthCountryCode());
        static::assertSame('972', $identity->birthDepartment());
        static::assertSame("L'HAY-LES-ROSES", $identity->birthCity());
    }

    public function testEveryFieldIsOptional(): void
    {
        $identity = AdministrativeIdentity::of(null, null, null, null, null, null, null, null);

        static::assertNull($identity->civility());
        static::assertNull($identity->birthName());
        static::assertNull($identity->usageName());
        static::assertNull($identity->firstName());
        static::assertNull($identity->birthDate());
        static::assertNull($identity->birthCountryCode());
        static::assertNull($identity->birthDepartment());
        static::assertNull($identity->birthCity());
    }

    public function testBoundariesAndAccentsAreAccepted(): void
    {
        $identity = AdministrativeIdentity::of(null, null, null, null, null, '', 'ééé', "Saint-Étienne d'Œuvre æ ÿ Ÿ ñ");
        $longCity = AdministrativeIdentity::of(null, null, null, null, null, 'ééé', '', str_repeat('é', 30));

        static::assertNull($identity->birthCountryCode());
        static::assertSame('ééé', $identity->birthDepartment());
        static::assertSame("Saint-Étienne d'Œuvre æ ÿ Ÿ ñ", $identity->birthCity());
        static::assertSame('ééé', $longCity->birthCountryCode());
        static::assertNull($longCity->birthDepartment());
        static::assertSame(str_repeat('é', 30), $longCity->birthCity());
    }

    public function testEmptyStringsAreAbsent(): void
    {
        $identity = AdministrativeIdentity::of(null, null, null, null, null, '', '', '');

        static::assertNull($identity->birthCountryCode());
        static::assertNull($identity->birthDepartment());
        static::assertNull($identity->birthCity());
        static::assertTrue($identity->isEmpty());
    }

    public function testEmptyNamesAreAbsent(): void
    {
        $empty = UpperCaseName::of('', 'name');
        $identity = AdministrativeIdentity::of(null, $empty, $empty, $empty, null, null, null, null);

        static::assertNull($identity->birthName());
        static::assertNull($identity->usageName());
        static::assertNull($identity->firstName());
        static::assertTrue($identity->isEmpty());
    }

    public function testPersonalParametersAreSensitive(): void
    {
        DomainAssertions::assertSensitiveParameters(AdministrativeIdentity::class, 'of', ['birthDate', 'birthCountryCode', 'birthDepartment', 'birthCity']);
        DomainAssertions::assertSensitiveParameters(AdministrativeIdentity::class, '__construct', ['birthDate', 'birthCountryCode', 'birthDepartment', 'birthCity']);
    }

    public function testIsEmptyOnlyWhenNoFieldIsSet(): void
    {
        $name = UpperCaseName::of('DUPONT', 'birth_name');
        $date = CalendarDate::fromString('1980-01-30');

        static::assertTrue(AdministrativeIdentity::of(null, null, null, null, null, null, null, null)->isEmpty());
        static::assertFalse(AdministrativeIdentity::of(Civility::mister(), null, null, null, null, null, null, null)->isEmpty());
        static::assertFalse(AdministrativeIdentity::of(null, $name, null, null, null, null, null, null)->isEmpty());
        static::assertFalse(AdministrativeIdentity::of(null, null, $name, null, null, null, null, null)->isEmpty());
        static::assertFalse(AdministrativeIdentity::of(null, null, null, $name, null, null, null, null)->isEmpty());
        static::assertFalse(AdministrativeIdentity::of(null, null, null, null, $date, null, null, null)->isEmpty());
        static::assertFalse(AdministrativeIdentity::of(null, null, null, null, null, '100', null, null)->isEmpty());
        static::assertFalse(AdministrativeIdentity::of(null, null, null, null, null, null, '972', null)->isEmpty());
        static::assertFalse(AdministrativeIdentity::of(null, null, null, null, null, null, null, 'PARIS')->isEmpty());
    }

    /**
     * @return iterable<string, array{string|null, string|null, string|null, list<string>}>
     */
    public static function provideInvalidInputs(): iterable
    {
        yield 'country too long' => ['1000', null, null, ['error birthCountryCode birth_country_code.too_long']];
        yield 'department too long' => [null, '9720', null, ['error birthDepartment birth_department.too_long']];
        yield 'city too long' => [null, null, str_repeat('A', 31), ['error birthCity birth_city.too_long']];
        yield 'city multibyte too long' => [null, null, str_repeat('é', 31), ['error birthCity birth_city.too_long']];
        yield 'city digit' => [null, null, 'PARIS 15', ['error birthCity birth_city.invalid_format']];
        yield 'city digit at the end' => [null, null, 'PARIS1', ['error birthCity birth_city.invalid_format']];
        yield 'city digit at the start' => [null, null, '1PARIS', ['error birthCity birth_city.invalid_format']];
        yield 'city punctuation' => [null, null, 'PARIS.', ['error birthCity birth_city.invalid_format']];
        yield 'all at once' => [
            '1000',
            '9720',
            '1',
            [
                'error birthCountryCode birth_country_code.too_long',
                'error birthDepartment birth_department.too_long',
                'error birthCity birth_city.invalid_format',
            ],
        ];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('provideInvalidInputs')]
    public function testOfReportsEveryViolation(?string $country, ?string $department, ?string $city, array $expected): void
    {
        try {
            AdministrativeIdentity::of(null, null, null, null, null, $country, $department, $city);
            static::fail('Expected a ValidationFailed.');
        } catch (ValidationFailed $failed) {
            static::assertSame($expected, DomainAssertions::summary($failed->result()));
        }
    }

    public function testIsMaskedInDebugOutput(): void
    {
        $identity = AdministrativeIdentity::of(null, UpperCaseName::of('SECRETNAME', 'birth_name'), null, null, null, null, null, 'SECRETCITY');
        $dump = print_r($identity, true);

        static::assertStringNotContainsString('SECRET', $dump);
        static::assertStringContainsString('***', $dump);
    }
}
