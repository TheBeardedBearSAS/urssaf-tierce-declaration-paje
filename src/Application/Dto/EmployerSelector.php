<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\PajeEmployerNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\ExternalReference;

/**
 * Designates an employer account of a tenant: by account id, by the host external reference or by Pajemploi number.
 */
final readonly class EmployerSelector
{
    private function __construct(
        #[SensitiveParameter]
        public EmployerAccountId|ExternalReference|PajeEmployerNumber $reference,
    ) {}

    public static function byAccountId(EmployerAccountId $accountId): self
    {
        return new self($accountId);
    }

    public static function byExternalReference(ExternalReference $externalReference): self
    {
        return new self($externalReference);
    }

    public static function byEmployerNumber(#[SensitiveParameter] PajeEmployerNumber $employerNumber): self
    {
        return new self($employerNumber);
    }

    /**
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return ['reference' => '***'];
    }
}
