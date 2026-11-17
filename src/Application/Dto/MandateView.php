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

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\Mandate;

/**
 * Read model of a mandate (primitives only: no employer number, no birth date). Instants are ISO 8601 UTC strings.
 */
final readonly class MandateView
{
    private function __construct(
        public string $mandateId,
        public string $tenantId,
        public string $externalReference,
        public string $employerAccountId,
        public string $origin,
        public string $state,
        public string $registeredAt,
        public ?string $cancelledAt,
        public string $updatedAt,
    ) {}

    public static function fromMandate(Mandate $mandate): self
    {
        return new self(
            $mandate->id()->value(),
            $mandate->tenantId()->value(),
            $mandate->externalReference()->value(),
            $mandate->employerAccountId()->value(),
            $mandate->origin()->value,
            $mandate->state()->value,
            $mandate->registeredAt()->toIso8601(),
            $mandate->cancelledAt()?->toIso8601(),
            $mandate->updatedAt()->toIso8601(),
        );
    }
}
