<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command;

use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\MandateView;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service\EmployerLocator;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service\MandateRecorder;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerStage;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\Mandate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateRepository;

/**
 * Records a mandate without any call. Idempotent and complete: a local active mandate is returned as is, and when the
 * account stage does not follow it yet ("mandate_registered"), the account is completed. This is the call-free recovery
 * after an unknown outcome of 030 that pajemploi shows registered, and after "mandate.recording_failed".
 *
 * Like the other mandate commands it reads again and retries (at most {@see MandateRecorder::MAX_ATTEMPTS} attempts) on a
 * conflict of the account or the mandate.
 */
final readonly class ImportMandateHandler
{
    public function __construct(
        private EmployerLocator $locator,
        private MandateRepository $mandates,
        private MandateRecorder $recorder,
    ) {}

    /**
     * @throws RecordNotFound     "employer_account.not_found"
     * @throws InvariantViolation "employer_account.concurrent_modification", "mandate.concurrent_modification" or
     *                            "mandate.duplicate_active" after every attempt (rolled back with a transactional
     *                            TransactionManager): call it again, it is idempotent; or a violation that is not a conflict
     */
    public function __invoke(ImportMandate $command): MandateView
    {
        $account = $this->locator->locate($command->tenantId, $command->employer);
        $active = $this->mandates->findActive($account->tenantId(), $account->employerNumber());
        if ($active instanceof Mandate && $account->stage() === EmployerStage::MandateRegistered) {
            return MandateView::fromMandate($active);
        }

        return MandateView::fromMandate($this->recorder->recordImport($account->id()));
    }
}
