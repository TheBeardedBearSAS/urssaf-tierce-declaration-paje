<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Doctrine\Contract;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecord;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationResult;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationSnapshot;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\OutcomeUnknownReason;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\SubmissionError;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerStage;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerVerification;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\OnlineAccountState;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\PajeEmployerNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\Mandate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateState;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Instant;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Declaration\RecordSamples;

/**
 * Builders shared by the repository contracts: numbered aggregates (account-1, mandate-1, record-1) of the employer
 * number n, in tenant-1 unless told otherwise. Aggregates are returned with their events released, except the pending*
 * builders, which keep the creation event.
 *
 * Employer 1 is the sample employer of the domain tests, the other ones are derived from their number.
 *
 * @mago-expect lint:too-many-methods
 */
final class ContractSamples
{
    public const AT = '2026-11-10T09:00:00Z';
    public const TENANT = 'tenant-1';
    public const OTHER_TENANT = 'tenant-2';

    private function __construct() {}

    public static function tenant(string $tenant = self::TENANT): TenantId
    {
        return TenantId::fromString($tenant);
    }

    public static function at(string $iso8601 = self::AT): Instant
    {
        return Instant::fromIso8601($iso8601);
    }

    public static function employerNumber(int $number): PajeEmployerNumber
    {
        return $number === 1 ? RecordSamples::employerNumber() : PajeEmployerNumber::of(\sprintf('Y%013d', $number));
    }

    public static function employer(int $number): EmployerReference
    {
        return EmployerReference::of(self::employerNumber($number), CalendarDate::fromString('1975-01-30'));
    }

    public static function pendingAccount(int $number, string $tenant = self::TENANT, string $at = self::AT): EmployerAccount
    {
        return self::pendingAccountOfEmployer($number, $number, $tenant, $at);
    }

    public static function pendingAccountOfEmployer(int $employer, int $number, string $tenant = self::TENANT, string $at = self::AT): EmployerAccount
    {
        return EmployerAccount::track(
            EmployerAccountId::fromString('account-' . $number),
            self::tenant($tenant),
            ExternalReference::fromString('employer-' . $employer),
            self::employer($employer),
            self::at($at),
        );
    }

    public static function account(
        int $number,
        EmployerStage $stage = EmployerStage::Tracked,
        string $tenant = self::TENANT,
        string $at = self::AT,
    ): EmployerAccount {
        return self::accountOfEmployer($number, $number, $stage, $tenant, $at);
    }

    public static function accountOfEmployer(
        int $employer,
        int $number,
        EmployerStage $stage = EmployerStage::Tracked,
        string $tenant = self::TENANT,
        string $at = self::AT,
    ): EmployerAccount {
        $account = self::pendingAccountOfEmployer($employer, $number, $tenant, $at);
        $now = self::at($at);
        if ($stage === EmployerStage::MandateRegistered) {
            $account->markMandateRegistered($now);
        }
        if ($stage === EmployerStage::MandateCancelled) {
            $account->markMandateCancelled($now);
        }
        $account->releaseEvents();

        return $account;
    }

    public static function verification(bool $authorised = true, string $state = 'Actif'): EmployerVerification
    {
        return EmployerVerification::of($authorised, OnlineAccountState::of($state));
    }

    public static function checkedAccount(int $number, string $checkedAt, string $tenant = self::TENANT, string $at = self::AT): EmployerAccount
    {
        $account = self::pendingAccount($number, $tenant, $at);
        $account->applyVerification(self::verification(false, 'Inconnu'), self::at($checkedAt));
        $account->releaseEvents();

        return $account;
    }

    public static function pendingMandate(int $number, string $tenant = self::TENANT, string $at = self::AT): Mandate
    {
        return self::pendingMandateOfEmployer($number, $number, $tenant, $at);
    }

    public static function pendingMandateOfEmployer(int $employer, int $number, string $tenant = self::TENANT, string $at = self::AT): Mandate
    {
        return Mandate::register(
            MandateId::fromString('mandate-' . $number),
            self::tenant($tenant),
            ExternalReference::fromString('employer-' . $employer),
            self::employer($employer),
            EmployerAccountId::fromString('account-' . $employer),
            self::at($at),
        );
    }

    public static function mandate(
        int $number,
        MandateState $state = MandateState::Registered,
        string $tenant = self::TENANT,
        string $at = self::AT,
    ): Mandate {
        return self::mandateOfEmployer($number, $number, $state, $tenant, $at);
    }

    public static function mandateOfEmployer(
        int $employer,
        int $number,
        MandateState $state = MandateState::Registered,
        string $tenant = self::TENANT,
        string $at = self::AT,
    ): Mandate {
        $mandate = self::pendingMandateOfEmployer($employer, $number, $tenant, $at);
        if ($state === MandateState::Cancelled) {
            $mandate->cancel(Instant::fromDateTime(self::at($at)->toDateTimeImmutable()->modify('+1 hour')));
        }
        $mandate->releaseEvents();

        return $mandate;
    }

    public static function pendingRecord(int $number, string $tenant = self::TENANT, string $at = self::AT): DeclarationRecord
    {
        return self::pendingRecordOfReference($number, $number, $tenant, $at);
    }

    public static function pendingRecordOfReference(int $reference, int $number, string $tenant = self::TENANT, string $at = self::AT, int $employer = 1): DeclarationRecord
    {
        return DeclarationRecord::record(
            DeclarationRecordId::fromString('record-' . $number),
            self::tenant($tenant),
            ExternalReference::fromString('declaration-' . $reference),
            self::snapshotOfEmployer($employer),
            self::at($at),
            null,
        );
    }

    public static function snapshotOfEmployer(int $employer): DeclarationSnapshot
    {
        return DeclarationSnapshot::fromDeclaration(self::employerNumber($employer), RecordSamples::childminder());
    }

    public static function recordOfEmployer(int $employer, int $number, DeclarationState $state, string $tenant = self::TENANT, string $at = self::AT): DeclarationRecord
    {
        return self::recordOfReference($number, $number, $state, 1, $tenant, $at, $employer);
    }

    public static function record(
        int $number,
        DeclarationState $state,
        int $attempts = 1,
        string $tenant = self::TENANT,
        string $at = self::AT,
    ): DeclarationRecord {
        return self::recordOfReference($number, $number, $state, $attempts, $tenant, $at);
    }

    /**
     * A record of the given state, reached through its real transitions (attempts started at $at).
     *
     * @mago-expect lint:excessive-parameter-list
     */
    public static function recordOfReference(
        int $reference,
        int $number,
        DeclarationState $state,
        int $attempts = 1,
        string $tenant = self::TENANT,
        string $at = self::AT,
        int $employer = 1,
    ): DeclarationRecord {
        $record = self::pendingRecordOfReference($reference, $number, $tenant, $at, $employer);
        $now = self::at($at);
        if ($state !== DeclarationState::Pending) {
            for ($attempt = 1; $attempt <= $attempts; ++$attempt) {
                $record->startSubmission($now);
                if ($attempt < $attempts) {
                    $record->markFailed([], $now);
                    $record->retry($now, max(1, $attempts));
                }
            }
        }
        if ($state === DeclarationState::Declared) {
            $record->markDeclared(self::result($reference), $now);
        }
        if ($state === DeclarationState::Rejected) {
            $record->markRejected(self::errors(), $now);
        }
        if ($state === DeclarationState::AlreadyDeclared) {
            $record->markAlreadyDeclared(self::errors(), $now);
        }
        if ($state === DeclarationState::OutcomeUnknown) {
            $record->markOutcomeUnknown(OutcomeUnknownReason::MayHaveReachedUrssaf, [], $now);
        }
        if ($state === DeclarationState::Error) {
            $record->markFailed([SubmissionError::of('urssaf.unavailable', 'technical', 'The service is unavailable.', null)], $now);
        }
        if ($state === DeclarationState::InvalidCredentials) {
            $record->markInvalidCredentials($now);
        }
        $record->releaseEvents();

        return $record;
    }

    public static function result(int $number): DeclarationResult
    {
        return RecordSamples::result(\sprintf('REF-%05d', $number));
    }

    /**
     * @return list<SubmissionError>
     */
    public static function errors(): array
    {
        return [
            SubmissionError::of('ER_API_050_0070', 'duplicate_declaration', 'First message.', 'First description.'),
            SubmissionError::of('ER_API_050_0080', 'business_rule', 'Second message.', null),
        ];
    }
}
