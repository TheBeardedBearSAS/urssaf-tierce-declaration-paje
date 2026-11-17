<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Declaration;

use Closure;
use DateInterval;
use LogicException;
use PHPUnit\Framework\Assert;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Child\Child;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Child\ChildCheck;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Child\ChildEntitlementStatus;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\CalculationMode;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\ChildminderChild;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\ChildminderContractEnd;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\ChildminderDeclaration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\Declaration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationAmounts;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationCommon;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationPeriod;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecord;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationResult;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationSnapshot;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\HomeCareContractEnd;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\HomeCareDeclaration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\SubmissionError;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\WithholdingTax;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\DeclaredEmployee;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeByNir;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeByNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\Nir;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\PajeEmployeeNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\PajeEmployerNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvalidStateTransition;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Message\NonBlockingMessage;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Amount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Instant;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Rate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\UpperCaseName;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\DomainAssertions;

/**
 * Fictitious builders of declaration records, snapshots and declarations shared by the record tests.
 *
 * @mago-expect lint:too-many-methods
 */
final class RecordSamples
{
    public static function instant(string $iso = '2026-11-15T10:00:00Z'): Instant
    {
        return Instant::fromIso8601($iso);
    }

    /**
     * An instant $minutes after the record creation instant.
     */
    public static function later(int $minutes): Instant
    {
        return Instant::fromDateTime(self::instant()->toDateTimeImmutable()->add(new DateInterval('PT' . $minutes . 'M')));
    }

    public static function tenantId(): TenantId
    {
        return TenantId::fromString('tenant-1');
    }

    public static function recordId(string $value = 'record-1'): DeclarationRecordId
    {
        return DeclarationRecordId::fromString($value);
    }

    public static function externalReference(): ExternalReference
    {
        return ExternalReference::fromString('decl-ref-1');
    }

    public static function employerNumber(): PajeEmployerNumber
    {
        return PajeEmployerNumber::of('Y1869005780009');
    }

    public static function employeeNumber(): PajeEmployeeNumber
    {
        return PajeEmployeeNumber::of('Y2926822480004');
    }

    public static function amount(string $decimal): Amount
    {
        return Amount::fromDecimalString($decimal);
    }

    public static function date(string $date): CalendarDate
    {
        return CalendarDate::fromString($date);
    }

    public static function employee(): DeclaredEmployee
    {
        return EmployeeByNumber::of(self::employeeNumber(), self::date('1990-03-04'));
    }

    public static function nirEmployee(): DeclaredEmployee
    {
        return EmployeeByNir::of(Nir::of('275037512345678'));
    }

    public static function common(int $hours = 100, ?int $specificHours = 4, ?DeclarationPeriod $period = null): DeclarationCommon
    {
        try {
            return DeclarationCommon::of(
                $period ?? DeclarationSamples::period(),
                $hours,
                DeclarationSamples::halfDays(3),
                self::amount('3.5'),
                $specificHours,
                self::amount('10'),
                self::amount('500'),
                self::amount('12'),
            );
        } catch (ValidationFailed $failed) {
            throw new LogicException('Invalid sample declaration data.', 0, $failed);
        }
    }

    public static function period(string $start, string $end, string $payment): DeclarationPeriod
    {
        return DeclarationPeriod::of(self::date($start), self::date($end), self::date($payment));
    }

    /**
     * Asserts the action throws a domain exception with the code (the exception is checked, not discarded).
     *
     * @param Closure(): mixed $action
     *
     * @param-immediately-invoked-callable $action
     */
    public static function assertViolation(string $code, Closure $action): void
    {
        $exception = DomainAssertions::assertViolationCode($code, $action);
        Assert::assertSame($code, $exception->violationCode());
    }

    /**
     * Asserts the action throws an InvalidStateTransition between the two states of the record aggregate.
     *
     * @param Closure(): mixed $action
     *
     * @param-immediately-invoked-callable $action
     */
    public static function assertInvalidTransition(Closure $action, string $from, string $to): void
    {
        try {
            $action();
        } catch (InvalidStateTransition $exception) {
            Assert::assertSame($from, $exception->from());
            Assert::assertSame($to, $exception->to());
            Assert::assertSame('declaration_record', $exception->aggregate());

            return;
        }
        Assert::fail('Expected an InvalidStateTransition.');
    }

    /**
     * @mago-expect lint:excessive-parameter-list
     */
    public static function childminder(
        ?DeclaredEmployee $employee = null,
        ?DeclarationCommon $common = null,
        ?ChildminderContractEnd $contractEnd = null,
        ?int $disabledDays = null,
        string $childFirstName = 'JEAN',
        ?ChildminderChild $secondChild = null,
    ): ChildminderDeclaration {
        return ChildminderDeclaration::of(
            $common ?? self::common(),
            [
                ChildminderChild::of(self::child($childFirstName), $disabledDays, 3),
                ...($secondChild instanceof ChildminderChild ? [$secondChild] : []),
            ],
            $employee ?? self::employee(),
            $contractEnd,
            20,
            5,
            6,
            self::amount('7'),
            self::amount('8'),
        );
    }

    public static function homeCare(
        ?DeclaredEmployee $employee = null,
        ?DeclarationCommon $common = null,
        ?CalculationMode $mode = null,
        ?HomeCareContractEnd $contractEnd = null,
        ?int $overtime25 = 2,
    ): HomeCareDeclaration {
        return HomeCareDeclaration::of(
            $mode ?? CalculationMode::real(),
            $common ?? self::common(),
            [self::child('JEAN')],
            $employee ?? self::employee(),
            $contractEnd,
            self::amount('9'),
            $overtime25,
            3,
        );
    }

    /**
     * A flat-rate home care declaration with only its mandatory values.
     */
    public static function sparseHomeCare(): HomeCareDeclaration
    {
        return HomeCareDeclaration::of(
            CalculationMode::flatRate(),
            DeclarationCommon::of(DeclarationSamples::period(), 1, null, null, null, null, self::amount('1'), null),
            [self::child('JEAN')],
            self::employee(),
            null,
            null,
            null,
            null,
        );
    }

    private static function child(string $firstName): Child
    {
        return Child::of(
            self::date('2015-01-12'),
            UpperCaseName::of('DUPONT', 'child_last_name'),
            UpperCaseName::of($firstName, 'child_first_name'),
        );
    }

    public static function snapshot(?Declaration $declaration = null): DeclarationSnapshot
    {
        return DeclarationSnapshot::fromDeclaration(self::employerNumber(), $declaration ?? self::childminder());
    }

    /**
     * A pending record whose creation event was released.
     */
    public static function record(?DeclarationSnapshot $snapshot = null, ?EmployerAccountId $accountId = null): DeclarationRecord
    {
        $record = DeclarationRecord::record(
            self::recordId(),
            self::tenantId(),
            self::externalReference(),
            $snapshot ?? self::snapshot(),
            self::instant(),
            $accountId,
        );
        $record->releaseEvents();

        return $record;
    }

    /**
     * @return list<SubmissionError>
     */
    public static function errors(): array
    {
        return [SubmissionError::of('ER_API_050_0070', 'duplicate_declaration', 'Déclaration déjà existante', 'Détail')];
    }

    public static function result(?string $reference = 'REF-001'): DeclarationResult
    {
        return DeclarationResult::of(
            $reference,
            DeclarationAmounts::of(
                self::amount('100'),
                null,
                null,
                WithholdingTax::of(null, Rate::fromString('5.5'), null, null),
                null,
                null,
                null,
                null,
                null,
                null,
            ),
            true,
            self::date('2026-11-20'),
            [ChildCheck::of(self::child('JEAN'), ChildEntitlementStatus::of(1))],
            [NonBlockingMessage::of('ER_API_DECLA_00007', 'Message', 'Description'), NonBlockingMessage::of('W2', null, null)],
        );
    }
}
