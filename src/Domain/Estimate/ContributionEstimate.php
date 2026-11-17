<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Estimate;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Message\NonBlockingMessage;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Amount;

/**
 * Result of a contribution estimate (`OutputEstimation`, api-reference §2.5). Every amount is optional and none is
 * bounded (ADR 0008); a 200 response may carry non-blocking messages.
 */
final readonly class ContributionEstimate
{
    /**
     * @param list<NonBlockingMessage> $messages
     */
    private function __construct(
        private ?Amount $coveredByFamilyBenefits,
        private ?Amount $employerShare,
        private ?Amount $employerExemption,
        private array $messages,
    ) {}

    /**
     * @param Amount|null              $coveredByFamilyBenefits `mntCotiPriseEnChargeOdpf`
     * @param Amount|null              $employerShare           `mntCotiEmplAcharge`
     * @param Amount|null              $employerExemption       `mntExoEmployeur`
     * @param list<NonBlockingMessage> $messages                `erreursNonBloquantes`
     */
    public static function of(
        ?Amount $coveredByFamilyBenefits,
        ?Amount $employerShare,
        ?Amount $employerExemption,
        array $messages,
    ): self {
        return new self($coveredByFamilyBenefits, $employerShare, $employerExemption, $messages);
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

    /**
     * `mntExoEmployeur`.
     */
    public function employerExemption(): ?Amount
    {
        return $this->employerExemption;
    }

    /**
     * @return list<NonBlockingMessage>
     */
    public function messages(): array
    {
        return $this->messages;
    }
}
