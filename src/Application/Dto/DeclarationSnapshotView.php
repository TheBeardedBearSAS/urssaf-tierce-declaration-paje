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

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationSnapshot;

/**
 * Read model of the non-personal summary kept with a declaration record (ADR 0007): activity type, period, how the employee
 * was identified and the fingerprint. The employer number is credential-like and left out (the host knows it from the
 * external reference or the tracked employer account); so are names, NIR, birth dates and amounts of the payload.
 * Dates are `yyyy-MM-dd`.
 */
final readonly class DeclarationSnapshotView
{
    private function __construct(
        public string $activity,
        public string $periodStart,
        public string $periodEnd,
        public string $paymentDate,
        public string $employeeIdentificationKind,
        public ?string $employeeNumber,
        public string $fingerprint,
    ) {}

    public static function fromSnapshot(DeclarationSnapshot $snapshot): self
    {
        $period = $snapshot->period();

        return new self(
            $snapshot->activity()->value,
            $period->start()->toString(),
            $period->end()->toString(),
            $period->paymentDate()->toString(),
            $snapshot->employeeIdentificationKind()->value,
            $snapshot->employeeNumber()?->value(),
            $snapshot->fingerprint()->value(),
        );
    }

    /**
     * @return array<string, string|null>
     */
    public function __debugInfo(): array
    {
        return [
            'activity' => $this->activity,
            'periodStart' => $this->periodStart,
            'periodEnd' => $this->periodEnd,
            'paymentDate' => $this->paymentDate,
            'employeeIdentificationKind' => $this->employeeIdentificationKind,
            'employeeNumber' => $this->employeeNumber === null ? null : '***',
            'fingerprint' => $this->fingerprint,
        ];
    }
}
