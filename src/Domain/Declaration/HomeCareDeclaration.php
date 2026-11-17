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
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Child\Child;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\DeclaredEmployee;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Amount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\AmountRules;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Validation\ViolationCollector;

/**
 * Home childcare (GED) declaration or pre-declaration body (`InputDeclarationGed`, api-reference §2.8). The child
 * list must not be empty (`inputDeclEnfant` is required in practice, ADR 0009); the contract publishes no maximum.
 *
 * Violation paths: "children", "transportCosts", "overtimeHours25", "overtimeHours50".
 */
final readonly class HomeCareDeclaration implements Declaration
{
    private const MAX_OVERTIME_HOURS_25 = 35;
    private const MAX_OVERTIME_HOURS_50 = 40;

    /**
     * @param list<Child> $children
     */
    private function __construct(
        private CalculationMode $calculationMode,
        private DeclarationCommon $common,
        private array $children,
        private DeclaredEmployee $employee,
        private ?HomeCareContractEnd $contractEnd,
        private ?Amount $transportCosts,
        private ?int $overtimeHours25,
        private ?int $overtimeHours50,
    ) {}

    /**
     * @param CalculationMode          $calculationMode `cdModeCalcul`
     * @param list<Child>              $children        `inputDeclEnfant`, not empty
     * @param HomeCareContractEnd|null $contractEnd     `finDeContrat`
     * @param Amount|null              $transportCosts  `mntFraisTransport`, at most 9999.99
     * @param int|null                 $overtimeHours25 `nbHrSupMajA25`, 0 to 35
     * @param int|null                 $overtimeHours50 `nbHrSupMajA50`, 0 to 40
     *
     * @throws ValidationFailed with every violation
     *
     * @mago-expect lint:excessive-parameter-list
     */
    public static function of(
        CalculationMode $calculationMode,
        DeclarationCommon $common,
        array $children,
        DeclaredEmployee $employee,
        ?HomeCareContractEnd $contractEnd,
        ?Amount $transportCosts,
        ?int $overtimeHours25,
        ?int $overtimeHours50,
    ): self {
        $collector = new ViolationCollector();
        if ($children === []) {
            $collector->add(InvalidValue::empty('children')->toViolation('children'));
        }
        AmountRules::capped($collector, 'transportCosts', 'transport_costs', $transportCosts);
        $collector->optionalInteger('overtimeHours25', 'overtime_hours_25', $overtimeHours25, 0, self::MAX_OVERTIME_HOURS_25);
        $collector->optionalInteger('overtimeHours50', 'overtime_hours_50', $overtimeHours50, 0, self::MAX_OVERTIME_HOURS_50);
        $collector->result()->throwIfInvalid();

        return new self(
            $calculationMode,
            $common,
            $children,
            $employee,
            $contractEnd,
            $transportCosts,
            $overtimeHours25,
            $overtimeHours50,
        );
    }

    #[Override]
    public function common(): DeclarationCommon
    {
        return $this->common;
    }

    /**
     * @return list<Child>
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
        return DeclarationActivity::HomeCare;
    }

    /**
     * `cdModeCalcul`.
     */
    public function calculationMode(): CalculationMode
    {
        return $this->calculationMode;
    }

    /**
     * `finDeContrat`.
     */
    public function contractEnd(): ?HomeCareContractEnd
    {
        return $this->contractEnd;
    }

    /**
     * `mntFraisTransport`.
     */
    public function transportCosts(): ?Amount
    {
        return $this->transportCosts;
    }

    /**
     * `nbHrSupMajA25`.
     */
    public function overtimeHours25(): ?int
    {
        return $this->overtimeHours25;
    }

    /**
     * `nbHrSupMajA50`.
     */
    public function overtimeHours50(): ?int
    {
        return $this->overtimeHours50;
    }
}
