<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Query;

use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\EmployerAccountView;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service\EmployerLocator;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\Mandate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateRepository;

/**
 * The mandate shown is the active one, else the most recent one (a cancelled mandate), else none.
 */
final readonly class GetEmployerAccountHandler
{
    public function __construct(
        private EmployerLocator $locator,
        private MandateRepository $mandates,
    ) {}

    /**
     * @throws RecordNotFound
     */
    public function __invoke(GetEmployerAccount $query): EmployerAccountView
    {
        $account = $this->locator->locate($query->tenantId, $query->employer);
        $mandate = $this->mandates->findActive($account->tenantId(), $account->employerNumber())
            ?? $this->mostRecent($this->mandates->findByEmployer($account->tenantId(), $account->employerNumber()));

        return EmployerAccountView::fromAccount($account, $mandate);
    }

    /**
     * @param list<Mandate> $mandates oldest registration first
     */
    private function mostRecent(array $mandates): ?Mandate
    {
        return $mandates === [] ? null : $mandates[\count($mandates) - 1];
    }
}
