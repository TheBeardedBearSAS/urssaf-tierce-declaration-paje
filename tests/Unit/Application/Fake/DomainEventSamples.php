<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Fake;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationActivity;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationPeriod;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\Event\DeclarationAlreadyDeclared;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\Event\DeclarationCredentialsRejected;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\Event\DeclarationDeclared;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\Event\DeclarationOutcomeUnknown;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\Event\DeclarationRecorded;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\Event\DeclarationRejected;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\Event\DeclarationRequeued;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\Event\DeclarationSubmissionFailed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\OutcomeUnknownReason;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerOrigin;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerStage;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerVerification;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\Event\EmployerForgotten;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\Event\EmployerRegistered;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\Event\EmployerStageChanged;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\Event\EmployerStatusRefreshed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\OnlineAccountState;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\Event\MandateCancelled;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\Event\MandateRegistered;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateOrigin;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Instant;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;

/**
 * Domain events built directly (fixed fictitious values), to test what is derived from them: one factory per event.
 *
 * @mago-expect lint:too-many-methods
 */
final class DomainEventSamples
{
    public const AT = '2026-11-15T10:00:00Z';

    private function __construct() {}

    public static function employerRegistered(): EmployerRegistered
    {
        return new EmployerRegistered(self::accountId(), self::tenant(), self::reference('employer-1'), EmployerOrigin::Tracked, self::at());
    }

    public static function employerStatusRefreshed(): EmployerStatusRefreshed
    {
        return new EmployerStatusRefreshed(
            self::accountId(),
            self::tenant(),
            self::reference('employer-1'),
            EmployerStage::MandateCancelled,
            EmployerVerification::of(true, OnlineAccountState::of('actif')),
            self::at(),
        );
    }

    public static function employerStageChanged(): EmployerStageChanged
    {
        return new EmployerStageChanged(
            self::accountId(),
            self::tenant(),
            self::reference('employer-1'),
            EmployerStage::Tracked,
            EmployerStage::MandateRegistered,
            self::at(),
        );
    }

    public static function employerForgotten(): EmployerForgotten
    {
        return new EmployerForgotten(
            self::accountId(),
            self::tenant(),
            self::reference('employer-1'),
            [self::mandateId(), MandateId::fromString('mandate-2')],
            [self::recordId(), DeclarationRecordId::fromString('record-2')],
            self::at(),
        );
    }

    public static function mandateRegistered(MandateOrigin $origin = MandateOrigin::Registered): MandateRegistered
    {
        return new MandateRegistered(self::mandateId(), self::tenant(), self::reference('employer-1'), self::accountId(), $origin, self::at());
    }

    public static function mandateCancelled(): MandateCancelled
    {
        return new MandateCancelled(self::mandateId(), self::tenant(), self::reference('employer-1'), self::accountId(), self::at());
    }

    public static function declarationRecorded(): DeclarationRecorded
    {
        return new DeclarationRecorded(
            self::recordId(),
            self::tenant(),
            self::reference('declaration-1'),
            DeclarationActivity::Childminder,
            self::period(),
            self::at(),
        );
    }

    public static function declarationDeclared(?string $documentReference = '2026-123456'): DeclarationDeclared
    {
        return self::declared($documentReference, self::accountId());
    }

    public static function declarationDeclaredOfUntrackedEmployer(?string $documentReference): DeclarationDeclared
    {
        return self::declared($documentReference, null);
    }

    private static function declared(?string $documentReference, ?EmployerAccountId $accountId): DeclarationDeclared
    {
        return new DeclarationDeclared(
            self::recordId(),
            self::tenant(),
            self::reference('declaration-1'),
            $accountId,
            DeclarationActivity::HomeCare,
            self::period(),
            $documentReference,
            ['AV_001', 'AV_002'],
            self::at(),
        );
    }

    public static function declarationRejected(): DeclarationRejected
    {
        return new DeclarationRejected(
            self::recordId(),
            self::tenant(),
            self::reference('declaration-1'),
            self::accountId(),
            DeclarationActivity::Childminder,
            self::period(),
            ['ER_API_050_0001', 'ER_API_050_0002'],
            self::at(),
        );
    }

    public static function declarationAlreadyDeclared(): DeclarationAlreadyDeclared
    {
        return new DeclarationAlreadyDeclared(
            self::recordId(),
            self::tenant(),
            self::reference('declaration-1'),
            self::accountId(),
            DeclarationActivity::Childminder,
            self::period(),
            ['ER_API_050_0070'],
            self::at(),
        );
    }

    public static function declarationRejectedOfUntrackedEmployer(): DeclarationRejected
    {
        return new DeclarationRejected(
            self::recordId(),
            self::tenant(),
            self::reference('declaration-1'),
            null,
            DeclarationActivity::Childminder,
            self::period(),
            ['ER_API_050_0001'],
            self::at(),
        );
    }

    public static function declarationAlreadyDeclaredOfUntrackedEmployer(): DeclarationAlreadyDeclared
    {
        return new DeclarationAlreadyDeclared(
            self::recordId(),
            self::tenant(),
            self::reference('declaration-1'),
            null,
            DeclarationActivity::Childminder,
            self::period(),
            ['ER_API_050_0070'],
            self::at(),
        );
    }

    public static function declarationOutcomeUnknown(): DeclarationOutcomeUnknown
    {
        return new DeclarationOutcomeUnknown(
            self::recordId(),
            self::tenant(),
            self::reference('declaration-1'),
            null,
            DeclarationActivity::HomeCare,
            self::period(),
            OutcomeUnknownReason::MayHaveReachedUrssaf,
            self::at(),
        );
    }

    public static function declarationSubmissionFailed(): DeclarationSubmissionFailed
    {
        return new DeclarationSubmissionFailed(
            self::recordId(),
            self::tenant(),
            ['urssaf.unavailable'],
            self::at(),
        );
    }

    public static function declarationCredentialsRejected(): DeclarationCredentialsRejected
    {
        return new DeclarationCredentialsRejected(self::recordId(), self::tenant(), self::at());
    }

    public static function declarationRequeued(): DeclarationRequeued
    {
        return new DeclarationRequeued(self::recordId(), self::tenant(), DeclarationState::Error, self::at());
    }

    public static function accountId(): EmployerAccountId
    {
        return EmployerAccountId::fromString('account-1');
    }

    private static function period(): DeclarationPeriod
    {
        return DeclarationPeriod::of(CalendarDate::fromString('2026-10-01'), CalendarDate::fromString('2026-10-31'), CalendarDate::fromString('2026-11-05'));
    }

    private static function tenant(): TenantId
    {
        return TenantId::fromString('tenant-1');
    }

    private static function reference(string $value): ExternalReference
    {
        return ExternalReference::fromString($value);
    }

    private static function mandateId(): MandateId
    {
        return MandateId::fromString('mandate-1');
    }

    private static function recordId(): DeclarationRecordId
    {
        return DeclarationRecordId::fromString('record-1');
    }

    private static function at(): Instant
    {
        return Instant::fromIso8601(self::AT);
    }
}
