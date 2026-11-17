<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Amount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\CalendarDate;

/**
 * Amounts computed by URSSAF for a (pre-)declaration (`DeclarationCommun`, api-reference §2.7). Every field is
 * optional and none is bounded (ADR 0008); the withholding fields are grouped in {@see WithholdingTax}.
 */
final readonly class DeclarationAmounts
{
    private function __construct(
        private ?Amount $contributions,
        private ?Amount $coveredByFamilyBenefits,
        private ?Amount $employerShare,
        private WithholdingTax $withholding,
        private ?Amount $cmgRemuneration,
        private ?Amount $payableToEmployee,
        private ?Amount $payableByPaje,
        private ?Amount $debitedByPaje,
        private ?CalendarDate $plannedDebitDate,
        private ?CalendarDate $plannedPaymentDate,
    ) {}

    /**
     * @param Amount|null       $contributions           `mntCotisations`
     * @param Amount|null       $coveredByFamilyBenefits `mntCotiPriseEnChargeOdpf`
     * @param Amount|null       $employerShare           `mntCotiEmplAcharge`
     * @param Amount|null       $cmgRemuneration         `mntEmplCmgRemuneration`
     * @param Amount|null       $payableToEmployee       `mntEmplAverserSalarie`, to be paid by the employer to the employee
     * @param Amount|null       $payableByPaje           `mntAverserParPaje`, to be paid to the employer by Pajemploi
     * @param Amount|null       $debitedByPaje           `mntApreleverParPaje`, to be debited from the employer by Pajemploi
     * @param CalendarDate|null $plannedDebitDate        `dtPrelevementPrevisionnelle`
     * @param CalendarDate|null $plannedPaymentDate      `dtVersementPrevisionnelle`
     *
     * @mago-expect lint:excessive-parameter-list
     */
    public static function of(
        ?Amount $contributions,
        ?Amount $coveredByFamilyBenefits,
        ?Amount $employerShare,
        WithholdingTax $withholding,
        ?Amount $cmgRemuneration,
        ?Amount $payableToEmployee,
        ?Amount $payableByPaje,
        ?Amount $debitedByPaje,
        ?CalendarDate $plannedDebitDate,
        ?CalendarDate $plannedPaymentDate,
    ): self {
        return new self(
            $contributions,
            $coveredByFamilyBenefits,
            $employerShare,
            $withholding,
            $cmgRemuneration,
            $payableToEmployee,
            $payableByPaje,
            $debitedByPaje,
            $plannedDebitDate,
            $plannedPaymentDate,
        );
    }

    /**
     * `mntCotisations`.
     */
    public function contributions(): ?Amount
    {
        return $this->contributions;
    }

    /**
     * `mntCotiPriseEnChargeOdpf`.
     */
    public function coveredByFamilyBenefits(): ?Amount
    {
        return $this->coveredByFamilyBenefits;
    }

    /**
     * `mntCotiEmplAcharge`.
     */
    public function employerShare(): ?Amount
    {
        return $this->employerShare;
    }

    public function withholding(): WithholdingTax
    {
        return $this->withholding;
    }

    /**
     * `mntEmplCmgRemuneration`.
     */
    public function cmgRemuneration(): ?Amount
    {
        return $this->cmgRemuneration;
    }

    /**
     * `mntEmplAverserSalarie`.
     */
    public function payableToEmployee(): ?Amount
    {
        return $this->payableToEmployee;
    }

    /**
     * `mntAverserParPaje`.
     */
    public function payableByPaje(): ?Amount
    {
        return $this->payableByPaje;
    }

    /**
     * `mntApreleverParPaje`.
     */
    public function debitedByPaje(): ?Amount
    {
        return $this->debitedByPaje;
    }

    /**
     * `dtPrelevementPrevisionnelle`.
     */
    public function plannedDebitDate(): ?CalendarDate
    {
        return $this->plannedDebitDate;
    }

    /**
     * `dtVersementPrevisionnelle`.
     */
    public function plannedPaymentDate(): ?CalendarDate
    {
        return $this->plannedPaymentDate;
    }
}
