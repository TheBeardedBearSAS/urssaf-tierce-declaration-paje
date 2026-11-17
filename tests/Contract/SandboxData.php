<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Contract;

use DateTimeImmutable;
use DateTimeZone;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Child\Child;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\CalculationMode;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\ChildminderChild;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\ChildminderDeclaration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationCommon;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationPeriod;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\HomeCareDeclaration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\ActivityType;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\Address;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\AdministrativeIdentity;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\Civility;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeByAdministrativeData;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeByNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeCreation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeIdentification;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\PajeEmployeeNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\PajeEmployerNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Estimate\ChildminderEstimateRequest;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Estimate\HomeCareEstimateRequest;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Estimate\YoungestChildAge;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Amount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\PostalCode;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\UpperCaseName;

/**
 * Fictitious data only (never a real person), unique per run where the sandbox stores it. The fictitious numbers are
 * assumed absent from the sandbox: an "unknown" answer is observed, not asserted.
 *
 * One builder per operation and per variant, hence the method count.
 *
 * @mago-expect lint:too-many-methods
 */
final class SandboxData
{
    public const FICTITIOUS_BIRTH_DATE = '1980-03-29';

    private const TIME_ZONE = 'Europe/Paris';
    private const FICTITIOUS_EMPLOYER_NUMBER = 'Z0000000000000';
    private const FICTITIOUS_EMPLOYEE_NUMBER = 'Z0000000000001';

    private function __construct() {}

    public static function fictitiousEmployer(): EmployerReference
    {
        return EmployerReference::of(PajeEmployerNumber::of(self::FICTITIOUS_EMPLOYER_NUMBER), CalendarDate::fromString(self::FICTITIOUS_BIRTH_DATE));
    }

    public static function fictitiousEmployeeNumber(): PajeEmployeeNumber
    {
        return PajeEmployeeNumber::of(self::FICTITIOUS_EMPLOYEE_NUMBER);
    }

    public static function fictitiousChild(): Child
    {
        return Child::of(
            CalendarDate::fromString('2020-02-02'),
            UpperCaseName::of('TESTCHILD', 'child_last_name'),
            UpperCaseName::of('TESTFIRST', 'child_first_name'),
        );
    }

    public static function today(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone(self::TIME_ZONE));
    }

    /**
     * 040 with fictitious values (a net salary of 500.00 for one child over 10 days).
     */
    public static function childminderEstimate(): ChildminderEstimateRequest
    {
        return ChildminderEstimateRequest::of(null, null, Amount::fromDecimalString('500'), 1, 10, null, null, null);
    }

    /**
     * 041 with fictitious values (real calculation mode, 100 hours, youngest child under 3 years).
     */
    public static function homeCareEstimate(): HomeCareEstimateRequest
    {
        return HomeCareEstimateRequest::of(
            null,
            null,
            Amount::fromDecimalString('500'),
            CalculationMode::real(),
            100,
            null,
            null,
            YoungestChildAge::underThree(),
            false,
        );
    }

    public static function byNumber(PajeEmployeeNumber $number): EmployeeIdentification
    {
        return EmployeeByNumber::of($number);
    }

    /**
     * A new fictitious identity per call (random upper-case letters): 023 stores it when it creates the employee.
     */
    public static function newIdentity(): AdministrativeIdentity
    {
        return AdministrativeIdentity::of(
            Civility::mister(),
            UpperCaseName::of('TEST' . self::letters(8), 'birth_name'),
            null,
            UpperCaseName::of('PRENOM' . self::letters(6), 'first_name'),
            CalendarDate::fromString('1985-06-15'),
            '100',
            '75',
            'PARIS',
        );
    }

    public static function byIdentity(AdministrativeIdentity $identity): EmployeeIdentification
    {
        return EmployeeByAdministrativeData::of($identity);
    }

    public static function address(): Address
    {
        return Address::of(null, null, null, 'RUE DE LA PAIX', null, null, '75056', PostalCode::fromString('75001'));
    }

    /**
     * The body of 023 for a creation with every field the PDF marks mandatory.
     */
    public static function completeCreation(AdministrativeIdentity $identity): EmployeeCreation
    {
        return self::creation($identity, self::address(), ActivityType::homeCare());
    }

    public static function creationWithoutAddress(AdministrativeIdentity $identity): EmployeeCreation
    {
        return self::creation($identity, null, ActivityType::homeCare());
    }

    public static function creationWithoutActivity(AdministrativeIdentity $identity): EmployeeCreation
    {
        return self::creation($identity, self::address(), null);
    }

    /**
     * The body of 023 when the employee is identified by administrative data and nothing else is given.
     */
    public static function identityOnlyCreation(AdministrativeIdentity $identity): EmployeeCreation
    {
        return EmployeeCreation::of(null, null, null, null, null, $identity);
    }

    public static function emptyCreation(): EmployeeCreation
    {
        return EmployeeCreation::of(null, null, null, null, null, null);
    }

    /**
     * The employment month the platform currently accepts: the one of today from the 25th (the declarative space opens
     * that day, PDF 4.4.1), the previous month before. The payment date is today, or the end of a past period.
     */
    public static function period(DateTimeImmutable $today): DeclarationPeriod
    {
        $month = (int) $today->format('j') >= 25 ? $today : $today->modify('first day of previous month');
        $start = $month->modify('first day of this month');
        $end = $month->modify('last day of this month');

        return DeclarationPeriod::of(
            CalendarDate::fromString($start->format('Y-m-d')),
            CalendarDate::fromString($end->format('Y-m-d')),
            CalendarDate::fromString(($today < $end ? $today : $end)->format('Y-m-d')),
        );
    }

    public static function childminderDeclaration(PajeEmployeeNumber $employee, Child $child, DateTimeImmutable $today): ChildminderDeclaration
    {
        return ChildminderDeclaration::of(
            self::common(self::period($today)),
            [ChildminderChild::of($child, null, null)],
            EmployeeByNumber::of($employee),
            null,
            10,
            null,
            null,
            null,
            null,
        );
    }

    public static function homeCareDeclaration(PajeEmployeeNumber $employee, Child $child, DateTimeImmutable $today): HomeCareDeclaration
    {
        return HomeCareDeclaration::of(
            CalculationMode::real(),
            self::common(self::period($today)),
            [$child],
            EmployeeByNumber::of($employee),
            null,
            null,
            null,
            null,
        );
    }

    /**
     * A creation body whose optional parts are given or left out, to observe which are really required.
     */
    private static function creation(AdministrativeIdentity $identity, ?Address $address, ?ActivityType $activity): EmployeeCreation
    {
        return EmployeeCreation::of($activity, CalendarDate::fromString('1985-06-15'), null, null, $address, $identity);
    }

    private static function common(DeclarationPeriod $period): DeclarationCommon
    {
        return DeclarationCommon::of($period, 100, null, null, null, null, Amount::fromDecimalString('500'), null);
    }

    private static function letters(int $length): string
    {
        $letters = '';
        for ($i = 0; $i < $length; ++$i) {
            $letters .= \chr(\ord('A') + random_int(0, 25));
        }

        return $letters;
    }
}
