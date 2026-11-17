<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Estimate\ContributionEstimate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Amount;

/**
 * Read model of a contribution estimate (methods 040 / 041): amounts as decimal euro strings with two decimals, null
 * when URSSAF did not return them.
 */
final readonly class ContributionEstimateView
{
    /**
     * @param list<NonBlockingMessageView> $messages
     */
    private function __construct(
        public ?string $coveredByFamilyBenefits,
        public ?string $employerShare,
        public ?string $employerExemption,
        public array $messages,
    ) {}

    public static function fromEstimate(ContributionEstimate $estimate): self
    {
        return new self(
            self::amount($estimate->coveredByFamilyBenefits()),
            self::amount($estimate->employerShare()),
            self::amount($estimate->employerExemption()),
            NonBlockingMessageView::fromMessages($estimate->messages()),
        );
    }

    private static function amount(?Amount $amount): ?string
    {
        return $amount?->toDecimalString();
    }
}
