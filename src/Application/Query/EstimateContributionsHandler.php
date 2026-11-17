<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Query;

use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\ContributionEstimateView;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\ContributionEstimator;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Estimate\ChildminderEstimateRequest;

/**
 * Methods 040 / 041 as a query: nothing is recorded by URSSAF or locally, every failure propagates (safe to retry).
 */
final readonly class EstimateContributionsHandler
{
    public function __construct(private ContributionEstimator $estimator) {}

    /**
     * @throws UrssafRequestRejected
     * @throws UrssafAuthenticationFailed
     * @throws UrssafUnavailable
     * @throws RateLimitExceeded
     */
    public function __invoke(EstimateContributions $query): ContributionEstimateView
    {
        $estimate = $query->request instanceof ChildminderEstimateRequest
            ? $this->estimator->estimateChildminder($query->tenantId, $query->request)
            : $this->estimator->estimateHomeCare($query->tenantId, $query->request);

        return ContributionEstimateView::fromEstimate($estimate);
    }
}
