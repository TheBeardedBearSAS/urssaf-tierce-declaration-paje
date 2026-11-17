<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Entity;

use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Type\MandateIdType;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Type\PajeEmployerNumberType;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Type\TenantIdType;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\PajeEmployerNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\Mandate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;

/**
 * Key row of a non-cancelled mandate: the primary key (tenant, employer number) is the "one active mandate per
 * employer" constraint (ADR 0006, ADR 0014). Mapped so that the schema tools and migrations know the table; written
 * with DBAL by {@see \TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Repository\DoctrineMandateRepository}
 * in the transaction of the mandate flush, never through the ORM.
 *
 * Not final only because Doctrine may generate a proxy for any mapped entity: final by convention, never extend it.
 *
 * @final
 */
class ActiveMandate
{
    /** DBAL types of the columns of {@see self::row()}. */
    public const TYPES = [
        'tenant_id' => TenantIdType::NAME,
        'employer_number' => PajeEmployerNumberType::NAME,
        'mandate_id' => MandateIdType::NAME,
    ];

    private function __construct(
        protected TenantId $tenantId,
        protected PajeEmployerNumber $employerNumber,
        private readonly MandateId $mandateId,
    ) {}

    public static function of(Mandate $mandate): self
    {
        return new self($mandate->tenantId(), $mandate->employerNumber(), $mandate->id());
    }

    /**
     * The columns of the row, keyed by column name (see {@see self::TYPES}).
     *
     * @return array{tenant_id: TenantId, employer_number: PajeEmployerNumber, mandate_id: MandateId}
     */
    public function row(): array
    {
        return [
            'tenant_id' => $this->tenantId,
            'employer_number' => $this->employerNumber,
            'mandate_id' => $this->mandateId,
        ];
    }
}
