<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command;

use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\EmployerErasure;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Exception\EmployerErasureRefused;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Clock;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service\EmployerLocator;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service\RecordedEvents;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service\UnitOfWork;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\Mandate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Instant;

/**
 * GDPR erasure of a tracked employer (ADR 0015): in one transaction, refuses what would break a workflow guarantee
 * (nothing is deleted then), deletes the declaration records, the mandates and, last, the account, checks that nothing of
 * the employer is left, and records the `EmployerForgotten` event (identifiers only, ADR 0007).
 *
 * Nothing is sent to URSSAF: no gateway is injected. The two opt-ins of the command default to the {@see WorkflowPolicy}.
 */
final readonly class ForgetEmployerHandler
{
    public function __construct(
        private EmployerLocator $locator,
        private EmployerAccountRepository $accounts,
        private MandateRepository $mandates,
        private DeclarationRecordRepository $records,
        private Clock $clock,
        private UnitOfWork $unitOfWork,
        private WorkflowPolicy $policy,
    ) {}

    /**
     * @throws RecordNotFound         "employer_account.not_found" (also for an account of another tenant)
     * @throws EmployerErasureRefused nothing was erased
     * @throws InvariantViolation     "employer_account.concurrent_modification" (rolled back), or a repository code
     */
    public function __invoke(ForgetEmployer $command): EmployerErasure
    {
        return $this->unitOfWork->run(function (RecordedEvents $events) use ($command): EmployerErasure {
            $account = $this->locator->locate($command->tenantId, $command->employer);
            $tenant = $account->tenantId();
            $employerNumber = $account->employerNumber();
            $allowUnknownOutcomes = $command->allowUnknownOutcomes ?? $this->policy->allowErasureWithUnknownOutcomes();
            $mandates = $this->mandates->findByEmployer($tenant, $employerNumber);

            $this->assertErasable(
                $account,
                $mandates,
                $this->records->countByEmployer($tenant, $employerNumber),
                $command->allowActiveMandate ?? $this->policy->allowErasureWithActiveMandate(),
                $allowUnknownOutcomes,
            );

            $recordIds = $this->records->deleteByEmployer($tenant, $employerNumber, self::erasableStates($allowUnknownOutcomes));
            $mandateIds = array_map(static fn(Mandate $mandate): MandateId => $mandate->id(), $mandates);
            foreach ($mandateIds as $mandateId) {
                $this->mandates->delete($mandateId);
            }
            $this->accounts->delete($account->id());
            $this->assertNothingLeft($account);

            $account->forget(Instant::fromDateTime($this->clock->now()), $mandateIds, $recordIds);
            $events->record(...$account->releaseEvents());

            return new EmployerErasure(
                $account->id()->value(),
                array_map(static fn(MandateId $id): string => $id->value(), $mandateIds),
                array_map(static fn(DeclarationRecordId $id): string => $id->value(), $recordIds),
            );
        });
    }

    /**
     * Every state but submitting, and but outcome_unknown unless allowed.
     *
     * @return non-empty-list<DeclarationState>
     *
     * @mago-expect lint:no-boolean-flag-parameter
     */
    private static function erasableStates(bool $allowUnknownOutcomes): array
    {
        $states = [];
        foreach (DeclarationState::cases() as $state) {
            if ($state === DeclarationState::Submitting || (!$allowUnknownOutcomes && $state === DeclarationState::OutcomeUnknown)) {
                continue;
            }
            $states[] = $state;
        }

        // @mago-expect analysis:invalid-return-statement The enum always has erasable states: mago does not infer a non-empty list.
        return $states;
    }

    /**
     * Checks in a deterministic order: active mandate, submission in flight, unknown outcome.
     *
     * @param list<Mandate>      $mandates
     * @param array<string, int> $counts   records of the employer per state
     *
     * @throws EmployerErasureRefused
     *
     * @mago-expect lint:no-boolean-flag-parameter
     */
    private function assertErasable(EmployerAccount $account, array $mandates, array $counts, bool $allowActiveMandate, bool $allowUnknownOutcomes): void
    {
        if (!$allowActiveMandate && array_filter($mandates, static fn(Mandate $mandate): bool => $mandate->isActive()) !== []) {
            throw EmployerErasureRefused::activeMandate($account->id());
        }

        $submitting = $counts[DeclarationState::Submitting->value];
        if ($submitting > 0) {
            throw EmployerErasureRefused::submissionInFlight($account->id(), $submitting);
        }

        $unknown = $counts[DeclarationState::OutcomeUnknown->value];
        if (!$allowUnknownOutcomes && $unknown > 0) {
            throw EmployerErasureRefused::unknownOutcomes($account->id(), $unknown);
        }
    }

    /**
     * @throws InvariantViolation "employer_account.concurrent_modification"
     */
    private function assertNothingLeft(EmployerAccount $account): void
    {
        $tenant = $account->tenantId();
        $employerNumber = $account->employerNumber();
        if (array_sum($this->records->countByEmployer($tenant, $employerNumber)) === 0 && $this->mandates->findByEmployer($tenant, $employerNumber) === []) {
            return;
        }

        throw InvariantViolation::because(
            'employer_account.concurrent_modification',
            'The employer was changed by another process while it was being erased: nothing was erased, run the erasure again.',
            ['id' => $account->id()->value()],
        );
    }
}
