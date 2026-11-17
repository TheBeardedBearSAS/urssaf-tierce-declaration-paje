<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Exception;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\DomainException;

/**
 * Thrown when ForgetEmployer is refused locally to keep a workflow guarantee (ADR 0015). Nothing was erased and nothing
 * was sent to URSSAF.
 */
final class EmployerErasureRefused extends DomainException
{
    public static function activeMandate(EmployerAccountId $accountId): self
    {
        return new self(
            'The employer still has an active mandate: cancel it first (method 031) or allow its erasure explicitly.',
            'employer_account.forget_active_mandate',
            ['employer_account_id' => $accountId->value()],
        );
    }

    public static function submissionInFlight(EmployerAccountId $accountId, int $count): self
    {
        return new self(
            \sprintf('%d declaration(s) of the employer are being submitted: wait for their outcome, then erase the employer.', $count),
            'employer_account.forget_submission_in_flight',
            ['employer_account_id' => $accountId->value(), 'count' => $count],
        );
    }

    public static function unknownOutcomes(EmployerAccountId $accountId, int $count): self
    {
        return new self(
            \sprintf('%d declaration(s) of the employer have an unknown outcome: resolve them first or allow their erasure explicitly.', $count),
            'employer_account.forget_unknown_outcome',
            ['employer_account_id' => $accountId->value(), 'count' => $count],
        );
    }
}
