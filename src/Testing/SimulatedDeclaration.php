<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Testing;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Child\Child;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\ChildminderChild;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\Declaration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationActivity;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationPeriod;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerReference;

/**
 * A declaration recorded by the simulator: only what the duplicate rule needs, never the personal data.
 *
 * The rule (docs/api-reference.md §3.6): a childminder declaration (`ER_API_050_0070`) duplicates a recorded one of
 * the same employer, employee and period for a child of the recorded ones; a home care declaration
 * (`ER_API_DECLA_00020`) duplicates a recorded one of the same employer and employee in the same calendar month.
 *
 * @internal
 */
final readonly class SimulatedDeclaration
{
    /**
     * @param list<string> $children {@see SimulatedKey::child()} of the children
     */
    private function __construct(
        public string $documentReference,
        private string $employer,
        private string $employee,
        private DeclarationActivity $activity,
        private DeclarationPeriod $period,
        private array $children,
    ) {}

    public static function of(EmployerReference $employer, Declaration $declaration, string $documentReference): self
    {
        return new self(
            $documentReference,
            $employer->number()->value(),
            SimulatedKey::employee($declaration->employee()),
            $declaration->activityType(),
            $declaration->common()->period(),
            self::childKeys($declaration),
        );
    }

    public function duplicates(EmployerReference $employer, Declaration $declaration): bool
    {
        if (
            $this->employer !== $employer->number()->value()
            || $this->activity !== $declaration->activityType()
            || $this->employee !== SimulatedKey::employee($declaration->employee())
        ) {
            return false;
        }

        $period = $declaration->common()->period();

        return match ($this->activity) {
            DeclarationActivity::Childminder => $this->period->equals($period)
                && array_intersect($this->children, self::childKeys($declaration)) !== [],
            DeclarationActivity::HomeCare => $this->period->start()->isSameMonthAs($period->start()),
        };
    }

    /**
     * @return list<string>
     */
    private static function childKeys(Declaration $declaration): array
    {
        return array_map(
            static fn(ChildminderChild|Child $child): string => SimulatedKey::child($child instanceof ChildminderChild ? $child->child() : $child),
            $declaration->children(),
        );
    }
}
