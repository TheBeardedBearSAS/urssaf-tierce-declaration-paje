<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Amount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\AmountRules;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Validation\ViolationCollector;

/**
 * Contract end indemnities of a childminder (`InputFinDeContratAma`, api-reference §2.7). Every amount is optional,
 * at most 9999.99, with no minimum in the Swagger. `enfants` is deprecated (PDF 1.5.1: no longer to be provided) but
 * kept optional: an empty list means "not sent".
 *
 * Source discrepancy (api-reference §5): the Swagger types `InputFinDeContratAma.enfants` as `InputEnfant` while the
 * PDF (§3.9 / §3.11) and §2.7 use `InputEnfantAma`. The PDF superset is kept as the laxer bound (both extra fields are
 * optional, §4 decision 11). For the request mapper: `joursAccueilHandicap` and `joursAccueil24Consecutive` are
 * emitted under `finDeContrat.enfants` only when set, exactly as under the top-level children.
 *
 * Violation paths: "retirementIndemnity", "paidLeaveCompensation", "noticeIndemnity", "precarityIndemnity",
 * "terminationIndemnity".
 */
final readonly class ChildminderContractEnd
{
    /**
     * @param list<ChildminderChild> $children
     */
    private function __construct(
        private ?Amount $retirementIndemnity,
        private ?Amount $paidLeaveCompensation,
        private ?Amount $noticeIndemnity,
        private ?Amount $precarityIndemnity,
        private ?Amount $terminationIndemnity,
        private array $children,
    ) {}

    /**
     * @param Amount|null            $retirementIndemnity   `mntIndDepartRetraite`
     * @param Amount|null            $paidLeaveCompensation `mntIndCcp`
     * @param Amount|null            $noticeIndemnity       `mntIndPreavis`
     * @param Amount|null            $precarityIndemnity    `mntIndPrecarite`
     * @param Amount|null            $terminationIndemnity  `mntIndRupture`
     * @param list<ChildminderChild> $children              `enfants` (deprecated)
     *
     * @throws ValidationFailed with every violation
     *
     * @mago-expect lint:excessive-parameter-list
     */
    public static function of(
        ?Amount $retirementIndemnity,
        ?Amount $paidLeaveCompensation,
        ?Amount $noticeIndemnity,
        ?Amount $precarityIndemnity,
        ?Amount $terminationIndemnity,
        array $children = [],
    ): self {
        $collector = new ViolationCollector();
        AmountRules::capped($collector, 'retirementIndemnity', 'retirement_indemnity', $retirementIndemnity);
        AmountRules::capped($collector, 'paidLeaveCompensation', 'paid_leave_compensation', $paidLeaveCompensation);
        AmountRules::capped($collector, 'noticeIndemnity', 'notice_indemnity', $noticeIndemnity);
        AmountRules::capped($collector, 'precarityIndemnity', 'precarity_indemnity', $precarityIndemnity);
        AmountRules::capped($collector, 'terminationIndemnity', 'termination_indemnity', $terminationIndemnity);
        $collector->result()->throwIfInvalid();

        return new self(
            $retirementIndemnity,
            $paidLeaveCompensation,
            $noticeIndemnity,
            $precarityIndemnity,
            $terminationIndemnity,
            $children,
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
     * `mntIndRupture`.
     */
    public function terminationIndemnity(): ?Amount
    {
        return $this->terminationIndemnity;
    }

    /**
     * `enfants` (deprecated).
     *
     * @return list<ChildminderChild>
     */
    public function children(): array
    {
        return $this->children;
    }
}
