<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Fake;

use Closure;
use LogicException;
use Override;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\ChildrenVerifier;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\ContributionEstimator;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\EmployeeAssociator;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\EmployeeVerifier;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\EmployerVerifier;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\MandateRegistrar;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Child\ChildCheck;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeAccount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeAssociation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeCreation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeIdentification;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerVerification;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Estimate\ChildminderEstimateRequest;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Estimate\ContributionEstimate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Estimate\HomeCareEstimateRequest;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;
use Throwable;

/**
 * Hand-written fake of the employer-side gateway ports: each operation answers what the test queued (a value, or a
 * throwable that is thrown), in order, and journals its call. An unexpected call fails the test.
 *
 * @mago-expect lint:too-many-methods
 */
final class FakeEmployerGateway implements
    EmployerVerifier,
    EmployeeVerifier,
    ChildrenVerifier,
    EmployeeAssociator,
    MandateRegistrar,
    ContributionEstimator
{
    /** @var array<string, list<mixed>> */
    private array $queues = [];

    /** @var list<string> */
    private array $calls = [];

    /** @var list<EmployerReference> */
    private array $employers = [];

    /** @var (Closure(): void)|null another process writing while the next call is in flight */
    private ?Closure $intrusion = null;

    public function __construct(private readonly Journal $journal) {}

    /**
     * Runs $intrusion once, during the next call (after it was journaled, before it answers): another process changing
     * the stored aggregates while the HTTP call is in flight.
     *
     * @param Closure(): void $intrusion
     */
    public function duringNextCall(Closure $intrusion): void
    {
        $this->intrusion = $intrusion;
    }

    public function queue(string $operation, mixed ...$outcomes): void
    {
        $this->queues[$operation] = [...($this->queues[$operation] ?? []), ...array_values($outcomes)];
    }

    /**
     * @return list<string>
     */
    public function calls(): array
    {
        return $this->calls;
    }

    /**
     * @return list<EmployerReference>
     */
    public function employers(): array
    {
        return $this->employers;
    }

    #[Override]
    public function verifyEmployer(TenantId $tenantId, EmployerReference $employer): EmployerVerification
    {
        $this->employers[] = $employer;

        return $this->answer('020', EmployerVerification::class);
    }

    #[Override]
    public function verifyEmployee(TenantId $tenantId, EmployeeIdentification $employee): EmployeeAccount
    {
        return $this->answer('021', EmployeeAccount::class);
    }

    #[Override]
    public function verifyChildren(TenantId $tenantId, EmployerReference $employer, array $children): array
    {
        return $this->answerChecks('022');
    }

    #[Override]
    public function associateEmployee(
        TenantId $tenantId,
        EmployerReference $employer,
        EmployeeIdentification $employee,
        EmployeeCreation $creation,
    ): EmployeeAssociation {
        return $this->answer('023', EmployeeAssociation::class);
    }

    #[Override]
    public function registerMandate(TenantId $tenantId, EmployerReference $employer): void
    {
        $this->outcome('030');
    }

    #[Override]
    public function cancelMandate(TenantId $tenantId, EmployerReference $employer): void
    {
        $this->outcome('031');
    }

    #[Override]
    public function estimateChildminder(TenantId $tenantId, ChildminderEstimateRequest $request): ContributionEstimate
    {
        return $this->answer('040', ContributionEstimate::class);
    }

    #[Override]
    public function estimateHomeCare(TenantId $tenantId, HomeCareEstimateRequest $request): ContributionEstimate
    {
        return $this->answer('041', ContributionEstimate::class);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $type
     *
     * @return T
     */
    private function answer(string $operation, string $type): object
    {
        // @mago-expect analysis:mixed-assignment The queued outcome is checked right below.
        $outcome = $this->outcome($operation);
        if (!$outcome instanceof $type) {
            throw new LogicException('Unexpected outcome queued for method ' . $operation . '.');
        }

        return $outcome;
    }

    /**
     * @return list<ChildCheck>
     */
    private function answerChecks(string $operation): array
    {
        // @mago-expect analysis:mixed-assignment The queued outcome is checked right below.
        $outcome = $this->outcome($operation);
        if (!\is_array($outcome) || !array_is_list($outcome)) {
            throw new LogicException('Unexpected outcome queued for method ' . $operation . '.');
        }

        return array_values(array_filter($outcome, static fn(mixed $check): bool => $check instanceof ChildCheck));
    }

    private function outcome(string $operation): mixed
    {
        $this->calls[] = $operation;
        $this->journal->add('call:' . $operation);
        if ($this->intrusion instanceof Closure) {
            $intrusion = $this->intrusion;
            $this->intrusion = null;
            $intrusion();
        }
        if (($this->queues[$operation] ?? []) === []) {
            throw new LogicException('Unexpected call of method ' . $operation . '.');
        }

        // @mago-expect analysis:mixed-assignment A queued outcome is anything the test chose: a value or a throwable.
        $outcome = array_shift($this->queues[$operation]);
        if ($outcome instanceof Throwable) {
            throw $outcome;
        }

        return $outcome;
    }
}
