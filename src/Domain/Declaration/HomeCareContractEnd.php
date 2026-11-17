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

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Amount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\AmountRules;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Validation\ViolationCollector;

/**
 * Contract end indemnities of a home childcare employee (`InputFinDeContratGed`, api-reference §2.8). Every amount is
 * optional, at most 9999.99, with no minimum in the Swagger. The Swagger field is `mntIndLicenciement` (the PDF
 * examples send `mntIndRupture`, which the Swagger does not know: api-reference §5).
 *
 * Violation paths: "retirementIndemnity", "paidLeaveCompensation", "noticeIndemnity", "precarityIndemnity",
 * "dismissalIndemnity".
 */
final readonly class HomeCareContractEnd
{
    private function __construct(
        private ?Amount $retirementIndemnity,
        private ?Amount $paidLeaveCompensation,
        private ?Amount $noticeIndemnity,
        private ?Amount $precarityIndemnity,
        private ?Amount $dismissalIndemnity,
    ) {}

    /**
     * @param Amount|null $retirementIndemnity   `mntIndDepartRetraite`
     * @param Amount|null $paidLeaveCompensation `mntIndCcp`
     * @param Amount|null $noticeIndemnity       `mntIndPreavis`
     * @param Amount|null $precarityIndemnity    `mntIndPrecarite`
     * @param Amount|null $dismissalIndemnity    `mntIndLicenciement`
     *
     * @throws ValidationFailed with every violation
     */
    public static function of(
        ?Amount $retirementIndemnity,
        ?Amount $paidLeaveCompensation,
        ?Amount $noticeIndemnity,
        ?Amount $precarityIndemnity,
        ?Amount $dismissalIndemnity,
    ): self {
        $collector = new ViolationCollector();
        AmountRules::capped($collector, 'retirementIndemnity', 'retirement_indemnity', $retirementIndemnity);
        AmountRules::capped($collector, 'paidLeaveCompensation', 'paid_leave_compensation', $paidLeaveCompensation);
        AmountRules::capped($collector, 'noticeIndemnity', 'notice_indemnity', $noticeIndemnity);
        AmountRules::capped($collector, 'precarityIndemnity', 'precarity_indemnity', $precarityIndemnity);
        AmountRules::capped($collector, 'dismissalIndemnity', 'dismissal_indemnity', $dismissalIndemnity);
        $collector->result()->throwIfInvalid();

        return new self(
            $retirementIndemnity,
            $paidLeaveCompensation,
            $noticeIndemnity,
            $precarityIndemnity,
            $dismissalIndemnity,
        );
    }

    /**
     * `mntIndDepartRetraite`.
     */
    public function retirementIndemnity(): ?Amount
    {
        return $this->retirementIndemnity;
    }

    /**
     * `mntIndCcp`.
     */
    public function paidLeaveCompensation(): ?Amount
    {
        return $this->paidLeaveCompensation;
    }

    /**
     * `mntIndPreavis`.
     */
    public function noticeIndemnity(): ?Amount
    {
        return $this->noticeIndemnity;
    }

    /**
     * `mntIndPrecarite`.
     */
    public function precarityIndemnity(): ?Amount
    {
        return $this->precarityIndemnity;
    }

    /**
     * `mntIndLicenciement`.
     */
    public function dismissalIndemnity(): ?Amount
    {
        return $this->dismissalIndemnity;
    }
}
