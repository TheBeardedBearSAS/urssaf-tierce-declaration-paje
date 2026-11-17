<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command;

use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\EmployerAccountView;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service\EmployerLocator;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service\EmployerStatusRefresher;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateRepository;

/**
 * Method 020 for one tracked employer. Every failure of the call propagates and changes nothing.
 */
final readonly class SyncEmployerStatusHandler
{
    public function __construct(
        private EmployerLocator $locator,
        private EmployerStatusRefresher $refresher,
        private EmployerAccountRepository $accounts,
        private MandateRepository $mandates,
    ) {}

    /**
     * @throws RecordNotFound
     * @throws UrssafRequestRejected
     * @throws UrssafAuthenticationFailed
     * @throws UrssafUnavailable
     * @throws RateLimitExceeded
     * @throws InvariantViolation         "employer_account.concurrent_modification": the account changed since it was read, the answer was discarded
     */
    public function __invoke(SyncEmployerStatus $command): EmployerAccountView
    {
        $account = $this->locator->locate($command->tenantId, $command->employer);
        $this->refresher->refresh($account);

        return EmployerAccountView::fromAccount(
            $this->accounts->get($account->id()),
            $this->mandates->findActive($account->tenantId(), $account->employerNumber()),
        );
    }
}
