<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Doctrine\Mapping;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationActivity;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationFingerprint;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecord;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationSnapshot;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\OutcomeUnknownReason;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeIdentificationKind;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerStage;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\Mandate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateState;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Doctrine\Contract\ContractSamples;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Declaration\RecordSamples;

/**
 * Aggregates of the mapping round trips, by case name (see {@see DoctrineMappingTest}): fictitious values only, events
 * released.
 */
final class DoctrineMappingSamples
{
    private function __construct() {}

    /**
     * @param string $case "tracked", "imported", "verified", "verified-unknown" or "stage:<EmployerStage value>"
     */
    public static function account(string $case): EmployerAccount
    {
        if ($case === 'imported') {
            $account = EmployerAccount::import(
                EmployerAccountId::fromString('account-1'),
                ContractSamples::tenant(),
                ExternalReference::fromString('employer-1'),
                ContractSamples::employer(1),
                ContractSamples::at(),
            );
            $account->releaseEvents();

            return $account;
        }
        if (str_starts_with($case, 'stage:')) {
            return ContractSamples::account(1, EmployerStage::from(substr($case, \strlen('stage:'))));
        }
        $account = ContractSamples::account(1);
        if ($case === 'verified') {
            $account->applyVerification(ContractSamples::verification(true, 'Actif'), ContractSamples::at('2026-11-10T10:00:00Z'));
        }
        if ($case === 'verified-unknown') {
            $account->applyVerification(ContractSamples::verification(false, 'Etat-futur'), ContractSamples::at('2026-11-10T10:00:00Z'));
        }
        $account->releaseEvents();

        return $account;
    }

    /**
     * @param string $case "imported" or "state:<MandateState value>"
     */
    public static function mandate(string $case): Mandate
    {
        if ($case === 'imported') {
            $mandate = Mandate::import(
                MandateId::fromString('mandate-1'),
                ContractSamples::tenant(),
                ExternalReference::fromString('employer-1'),
                ContractSamples::employer(1),
                EmployerAccountId::fromString('account-1'),
                ContractSamples::at(),
            );
            $mandate->releaseEvents();

            return $mandate;
        }

        return ContractSamples::mandate(1, MandateState::from(substr($case, \strlen('state:'))));
    }

    /**
     * @param string $case "state:<DeclarationState value>", "unknown-stale", "home-care", "nir", "administrative" or
     *                     "with-account"
     */
    public static function record(string $case): DeclarationRecord
    {
        if (str_starts_with($case, 'state:')) {
            return ContractSamples::record(1, DeclarationState::from(substr($case, \strlen('state:'))), 2);
        }
        if ($case === 'unknown-stale') {
            $record = ContractSamples::record(1, DeclarationState::Submitting);
            $record->markOutcomeUnknown(OutcomeUnknownReason::StaleSubmission, [], ContractSamples::at('2026-11-10T12:00:00Z'));
            $record->releaseEvents();

            return $record;
        }
        $snapshot = match ($case) {
            'home-care' => DeclarationSnapshot::fromDeclaration(ContractSamples::employerNumber(1), RecordSamples::homeCare()),
            'nir' => DeclarationSnapshot::fromDeclaration(ContractSamples::employerNumber(1), RecordSamples::childminder(RecordSamples::nirEmployee())),
            'administrative' => new DeclarationSnapshot(
                ContractSamples::employerNumber(1),
                DeclarationActivity::HomeCare,
                RecordSamples::period('2026-10-01', '2026-10-31', '2026-11-05'),
                EmployeeIdentificationKind::AdministrativeData,
                null,
                DeclarationFingerprint::fromString(str_repeat('ab', 32)),
            ),
            default => ContractSamples::snapshotOfEmployer(1),
        };
        $record = DeclarationRecord::record(
            DeclarationRecordId::fromString('record-1'),
            ContractSamples::tenant(),
            ExternalReference::fromString('declaration-' . $case),
            $snapshot,
            ContractSamples::at(),
            $case === 'with-account' ? EmployerAccountId::fromString('account-1') : null,
        );
        $record->releaseEvents();

        return $record;
    }
}
