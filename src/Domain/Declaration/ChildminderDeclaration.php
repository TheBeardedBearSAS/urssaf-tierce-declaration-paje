<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration;

use Override;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\DeclaredEmployee;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Amount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\AmountRules;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Validation\ViolationCollector;

/**
 * Childminder (AMA) declaration or pre-declaration body (`InputDeclarationAma`, api-reference §2.7).
 *
 * The child list must not be empty (`inputDeclEnfant` is required in practice, ADR 0009). The "one child per
 * declaration" rule (`ER_API_DECLA_00040`) is server-side and not checked here.
 *
 * Violation paths: "children", "activityDays", "majoredHours", "complementaryHours", "maintenanceAllowance",
 * "mealAllowance".
 */
final readonly class ChildminderDeclaration implements Declaration
{
    private const MIN_ACTIVITY_DAYS = 1;
    private const MAX_ACTIVITY_DAYS = 99;
    private const MAX_EXTRA_HOURS = 999;

    /**
     * @param list<ChildminderChild> $children
     */
    private function __construct(
        private DeclarationCommon $common,
        private array $children,
        private DeclaredEmployee $employee,
        private ?ChildminderContractEnd $contractEnd,
        private int $activityDays,
        private ?int $majoredHours,
        private ?int $complementaryHours,
        private ?Amount $maintenanceAllowance,
        private ?Amount $mealAllowance,
    ) {}

    /**
     * @param list<ChildminderChild>      $children             `inputDeclEnfant`, not empty
     * @param ChildminderContractEnd|null $contractEnd          `finDeContrat`
     * @param int                         $activityDays         `nbJoursActivite`, 1 to 99
     * @param int|null                    $majoredHours         `nbHeuresMajorees`, 0 to 999
     * @param int|null                    $complementaryHours   `nbHeuresComplementaires`, 0 to 999
     * @param Amount|null                 $maintenanceAllowance `mntIndemnitesEntretien`, 0 to 9999.99
     * @param Amount|null                 $mealAllowance        `mntIndemnitesRepas`, at most 9999.99
     *
     * @throws ValidationFailed with every violation
     *
     * @mago-expect lint:excessive-parameter-list
     */
    public static function of(
        DeclarationCommon $common,
        array $children,
        DeclaredEmployee $employee,
        ?ChildminderContractEnd $contractEnd,
        int $activityDays,
        ?int $majoredHours,
        ?int $complementaryHours,
        ?Amount $maintenanceAllowance,
        ?Amount $mealAllowance,
    ): self {
        $collector = new ViolationCollector();
        if ($children === []) {
            $collector->add(InvalidValue::empty('children')->toViolation('children'));
        }
        $collector->optionalInteger(
            'activityDays',
            'activity_days',
            $activityDays,
            self::MIN_ACTIVITY_DAYS,
            self::MAX_ACTIVITY_DAYS,
        );
        $collector->optionalInteger('majoredHours', 'majored_hours', $majoredHours, 0, self::MAX_EXTRA_HOURS);
        $collector->optionalInteger(
            'complementaryHours',
            'complementary_hours',
            $complementaryHours,
            0,
            self::MAX_EXTRA_HOURS,
        );
        AmountRules::nonNegativeCapped($collector, 'maintenanceAllowance', 'maintenance_allowance', $maintenanceAllowance);
        AmountRules::capped($collector, 'mealAllowance', 'meal_allowance', $mealAllowance);
        $collector->result()->throwIfInvalid();

        return new self(
            $common,
            $children,
            $employee,
            $contractEnd,
            $activityDays,
            $majoredHours,
            $complementaryHours,
            $maintenanceAllowance,
            $mealAllowance,
        );
    }

    #[Override]
    public function common(): DeclarationCommon
    {
        return $this->common;
    }

    /**
     * @return list<ChildminderChild>
     */
    #[Override]
    public function children(): array
    {
        return $this->children;
    }

    #[Override]
    public function employee(): DeclaredEmployee
    {
        return $this->employee;
    }

    #[Override]
    public function activityType(): DeclarationActivity
    {
        return DeclarationActivity::Childminder;
    }

    /**
     * `finDeContrat`.
     */
    public function contractEnd(): ?ChildminderContractEnd
    {
        return $this->contractEnd;
    }

    /**
     * `nbJoursActivite`.
     */
    public function activityDays(): int
    {
        return $this->activityDays;
    }

    /**
     * `nbHeuresMajorees`.
     */
    public function majoredHours(): ?int
    {
        return $this->majoredHours;
    }

    /**
     * `nbHeuresComplementaires`.
     */
    public function complementaryHours(): ?int
    {
        return $this->complementaryHours;
    }

    /**
     * `mntIndemnitesEntretien`.
     */
    public function maintenanceAllowance(): ?Amount
    {
        return $this->maintenanceAllowance;
    }

    /**
     * `mntIndemnitesRepas`.
     */
    public function mealAllowance(): ?Amount
    {
        return $this->mealAllowance;
    }
}
