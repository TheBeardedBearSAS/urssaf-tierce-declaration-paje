<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Employer;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerVerification;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\OnlineAccountState;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\PajeEmployerNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Instant;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;

/**
 * Fictitious values of the official documentation examples (api-reference §2.1).
 */
final class EmployerSamples
{
    public static function instant(string $iso = '2026-11-15T10:00:00Z'): Instant
    {
        return Instant::fromIso8601($iso);
    }

    public static function tenantId(): TenantId
    {
        return TenantId::fromString('tenant-1');
    }

    public static function externalReference(): ExternalReference
    {
        return ExternalReference::fromString('employer-ref-1');
    }

    public static function accountId(): EmployerAccountId
    {
        return EmployerAccountId::fromString('account-1');
    }

    public static function employer(): EmployerReference
    {
        return EmployerReference::of(PajeEmployerNumber::of('Y1869005780009'), CalendarDate::fromString('1975-02-23'));
    }

    /**
     * A tracked account whose events were released.
     */
    public static function account(): EmployerAccount
    {
        $account = EmployerAccount::track(
            self::accountId(),
            self::tenantId(),
            self::externalReference(),
            self::employer(),
            self::instant(),
        );
        $account->releaseEvents();

        return $account;
    }

    public static function verification(bool $authorised = true, string $state = 'Actif'): EmployerVerification
    {
        return EmployerVerification::of($authorised, OnlineAccountState::of($state));
    }
}
