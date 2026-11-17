<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command;

use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\MandateView;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Exception\MandateRecordingFailed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service\EmployerLocator;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service\MandateRecorder;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateRepository;

/**
 * Records a mandate cancellation without any call. Idempotent: it cancels the latest local mandate when it is active, or,
 * when it is already cancelled, makes sure the account follows it and returns it. Safe to call again after
 * "mandate.recording_failed" (same attempts and conflict rules as {@see CancelMandateHandler}).
 */
final readonly class RecordMandateCancellationHandler
{
    public function __construct(
        private EmployerLocator $locator,
        private MandateRepository $mandates,
        private MandateRecorder $recorder,
    ) {}

    /**
     * @throws RecordNotFound         "mandate.not_found" when the employer never had a local mandate, "employer_account.not_found"
     * @throws MandateRecordingFailed "mandate.recording_failed": record it again later (a failure that is not a conflict is its previous exception)
     */
    public function __invoke(RecordMandateCancellation $command): MandateView
    {
        $account = $this->locator->locate($command->tenantId, $command->employer);
        $known = $this->mandates->findByEmployer($account->tenantId(), $account->employerNumber());
        $mandate = array_pop($known)
            ?? throw RecordNotFound::withIdentifier('mandate', $account->id()->value());

        return MandateView::fromMandate($this->recorder->recordCancellation($account->id(), $mandate->id()));
    }
}
