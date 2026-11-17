<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Doctrine\Contract;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\Mandate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateOrigin;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateState;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\ExternalReference;

/**
 * Behaviour every {@see MandateRepository} must have, independently of the storage. Identity-related behaviour (copies
 * versus managed instances) is deliberately not part of the contract. One method per scenario.
 *
 * @phpstan-require-extends TestCase
 * @require-extends TestCase
 * @mago-expect lint:too-many-methods
 */
trait MandateRepositoryContract
{
    protected MandateRepository $repository;

    abstract protected function createRepository(): MandateRepository;

    protected function setUp(): void
    {
        $this->repository = $this->createRepository();
    }

    /**
     * @param list<Mandate> $mandates
     *
     * @return list<string>
     */
    protected static function mandateIds(array $mandates): array
    {
        return array_map(static fn(Mandate $mandate): string => $mandate->id()->value(), $mandates);
    }

    public function testARegisteredMandateIsReadBack(): void
    {
        $this->repository->save(ContractSamples::mandate(1));

        $read = $this->repository->get(MandateId::fromString('mandate-1'));

        static::assertSame('mandate-1', $read->id()->value());
        static::assertSame('tenant-1', $read->tenantId()->value());
        static::assertSame('employer-1', $read->externalReference()->value());
        static::assertTrue($read->employer()->equals(ContractSamples::employer(1)));
        static::assertSame(MandateOrigin::Registered, $read->origin());
        static::assertSame(MandateState::Registered, $read->state());
        static::assertSame('2026-11-10T09:00:00Z', $read->registeredAt()->toIso8601());
        static::assertNull($read->cancelledAt());
        static::assertSame('2026-11-10T09:00:00Z', $read->updatedAt()->toIso8601());
    }

    public function testAnImportedMandateIsReadBack(): void
    {
        $mandate = Mandate::import(
            MandateId::fromString('mandate-1'),
            ContractSamples::tenant(),
            ExternalReference::fromString('employer-1'),
            ContractSamples::employer(1),
            EmployerAccountId::fromString('account-1'),
            ContractSamples::at(),
        );
        $this->repository->save($mandate);

        static::assertSame(MandateOrigin::Imported, $this->repository->get($mandate->id())->origin());
    }

    public function testACancelledMandateIsReadBackWithItsTimeline(): void
    {
        $mandate = ContractSamples::mandate(1);
        $mandate->cancel(ContractSamples::at('2026-11-13T12:00:00Z'));
        $this->repository->save($mandate);

        $read = $this->repository->get($mandate->id());

        static::assertSame(MandateState::Cancelled, $read->state());
        static::assertSame('2026-11-10T09:00:00Z', $read->registeredAt()->toIso8601());
        static::assertSame('2026-11-13T12:00:00Z', $read->cancelledAt()?->toIso8601());
        static::assertSame('2026-11-13T12:00:00Z', $read->updatedAt()->toIso8601());
    }

    public function testTheEventsAreNeitherPersistedNorConsumedFromTheCaller(): void
    {
        $mandate = ContractSamples::pendingMandate(1);

        $this->repository->save($mandate);

        static::assertCount(1, $mandate->releaseEvents());
        static::assertSame([], $this->repository->get($mandate->id())->releaseEvents());
    }

    public function testAnUnknownMandateIsNotFound(): void
    {
        try {
            $this->repository->get(MandateId::fromString('missing'));
            static::fail('Expected RecordNotFound.');
        } catch (RecordNotFound $exception) {
            static::assertSame('mandate.not_found', $exception->violationCode());
            static::assertSame('missing', $exception->identifier());
        }
    }

    public function testASecondActiveMandateOfTheEmployerIsRefused(): void
    {
        $this->repository->save(ContractSamples::mandateOfEmployer(1, 1, MandateState::Registered, ContractSamples::TENANT, ContractSamples::AT));

        try {
            $this->repository->save(ContractSamples::mandateOfEmployer(1, 2, MandateState::Registered, ContractSamples::TENANT, ContractSamples::AT));
            static::fail('Expected a violation.');
        } catch (InvariantViolation $exception) {
            static::assertSame('mandate.duplicate_active', $exception->violationCode());
            // The employer number is credential-like (ADR 0007): never in an exception.
            static::assertSame([], $exception->params());
        }
    }

    public function testANewActiveMandateAfterACancelledOneIsAccepted(): void
    {
        $this->repository->save(ContractSamples::mandateOfEmployer(1, 1, MandateState::Cancelled, ContractSamples::TENANT, ContractSamples::AT));
        $this->repository->save(ContractSamples::mandateOfEmployer(1, 2, MandateState::Cancelled, ContractSamples::TENANT, '2026-11-11T09:00:00Z'));
        $this->repository->save(ContractSamples::mandateOfEmployer(1, 3, MandateState::Registered, ContractSamples::TENANT, '2026-11-12T09:00:00Z'));

        static::assertSame('mandate-3', $this->repository->findActive(ContractSamples::tenant(), ContractSamples::employerNumber(1))?->id()->value());
    }

    public function testTheActiveMandateOfAnotherTenantDoesNotConflict(): void
    {
        $this->repository->save(ContractSamples::mandateOfEmployer(1, 1, MandateState::Registered, ContractSamples::TENANT, ContractSamples::AT));
        $this->repository->save(ContractSamples::mandateOfEmployer(1, 2, MandateState::Registered, ContractSamples::OTHER_TENANT, ContractSamples::AT));

        static::assertSame('mandate-2', $this->repository->findActive(ContractSamples::tenant(ContractSamples::OTHER_TENANT), ContractSamples::employerNumber(1))?->id()->value());
    }

    public function testAnActiveMandateCanBeSavedAgain(): void
    {
        $mandate = ContractSamples::mandate(1);
        $this->repository->save($mandate);
        $this->repository->save($mandate);
        $mandate->cancel(ContractSamples::at('2026-11-11T10:00:00Z'));
        $this->repository->save($mandate);

        static::assertSame(MandateState::Cancelled, $this->repository->get($mandate->id())->state());
        static::assertSame(['mandate-1'], self::mandateIds($this->repository->findByEmployer(ContractSamples::tenant(), ContractSamples::employerNumber(1))));
    }

    public function testFindActiveIgnoresCancelledMandatesAndOtherTenants(): void
    {
        $tenant = ContractSamples::tenant();
        $employer = ContractSamples::employerNumber(1);
        $this->repository->save(ContractSamples::mandateOfEmployer(1, 1, MandateState::Cancelled, ContractSamples::TENANT, ContractSamples::AT));
        $this->repository->save(ContractSamples::mandateOfEmployer(1, 2, MandateState::Registered, ContractSamples::OTHER_TENANT, ContractSamples::AT));

        static::assertNull($this->repository->findActive($tenant, $employer));
        static::assertNull($this->repository->findActive($tenant, ContractSamples::employerNumber(2)));

        $this->repository->save(ContractSamples::mandateOfEmployer(1, 3, MandateState::Registered, ContractSamples::TENANT, '2026-11-11T09:00:00Z'));

        static::assertSame('mandate-3', $this->repository->findActive($tenant, $employer)?->id()->value());
    }

    public function testTheMandatesOfAnEmployerAreOrderedByRegistrationAndTenantScoped(): void
    {
        $this->repository->save(ContractSamples::mandateOfEmployer(1, 1, MandateState::Cancelled, ContractSamples::TENANT, '2026-11-12T09:00:00Z'));
        $this->repository->save(ContractSamples::mandateOfEmployer(1, 2, MandateState::Cancelled, ContractSamples::TENANT, '2026-11-10T09:00:00Z'));
        $this->repository->save(ContractSamples::mandateOfEmployer(1, 3, MandateState::Registered, ContractSamples::TENANT, '2026-11-14T09:00:00Z'));
        $this->repository->save(ContractSamples::mandateOfEmployer(1, 4, MandateState::Registered, ContractSamples::OTHER_TENANT, '2026-11-11T09:00:00Z'));
        $this->repository->save(ContractSamples::mandateOfEmployer(2, 5, MandateState::Registered, ContractSamples::TENANT, '2026-11-11T09:00:00Z'));
        $tenant = ContractSamples::tenant();

        static::assertSame(['mandate-2', 'mandate-1', 'mandate-3'], self::mandateIds($this->repository->findByEmployer($tenant, ContractSamples::employerNumber(1))));
        static::assertSame(['mandate-4'], self::mandateIds($this->repository->findByEmployer(ContractSamples::tenant(ContractSamples::OTHER_TENANT), ContractSamples::employerNumber(1))));
        static::assertSame([], $this->repository->findByEmployer($tenant, ContractSamples::employerNumber(3)));
    }

    public function testADeletedMandateIsGoneAndDeletingAMissingOneIsANoOp(): void
    {
        $this->repository->save(ContractSamples::mandate(1));
        $id = MandateId::fromString('mandate-1');
        $this->repository->get($id);

        $this->repository->delete($id);
        $this->repository->delete(MandateId::fromString('missing'));

        static::assertSame([], $this->repository->findByEmployer(ContractSamples::tenant(), ContractSamples::employerNumber(1)));
        $this->expectException(RecordNotFound::class);
        $this->repository->get($id);
    }

    public function testDeletingTheActiveMandateLetsTheEmployerHoldANewActiveMandate(): void
    {
        $this->repository->save(ContractSamples::mandateOfEmployer(1, 1, MandateState::Registered));

        $this->repository->delete(MandateId::fromString('mandate-1'));
        $this->repository->save(ContractSamples::mandateOfEmployer(1, 2, MandateState::Registered, ContractSamples::TENANT, '2026-11-12T09:00:00Z'));

        static::assertSame('mandate-2', $this->repository->findActive(ContractSamples::tenant(), ContractSamples::employerNumber(1))?->id()->value());
    }

    public function testDeletingAMandateKeepsTheOtherMandates(): void
    {
        $this->repository->save(ContractSamples::mandateOfEmployer(1, 1, MandateState::Cancelled));
        $this->repository->save(ContractSamples::mandateOfEmployer(1, 2, MandateState::Registered, ContractSamples::TENANT, '2026-11-12T09:00:00Z'));
        $this->repository->save(ContractSamples::mandateOfEmployer(2, 3, MandateState::Registered));
        $this->repository->save(ContractSamples::mandateOfEmployer(1, 4, MandateState::Registered, ContractSamples::OTHER_TENANT));

        $this->repository->delete(MandateId::fromString('mandate-1'));

        static::assertSame(['mandate-2'], self::mandateIds($this->repository->findByEmployer(ContractSamples::tenant(), ContractSamples::employerNumber(1))));
        static::assertSame(['mandate-3'], self::mandateIds($this->repository->findByEmployer(ContractSamples::tenant(), ContractSamples::employerNumber(2))));
        static::assertSame(['mandate-4'], self::mandateIds($this->repository->findByEmployer(ContractSamples::tenant(ContractSamples::OTHER_TENANT), ContractSamples::employerNumber(1))));
        static::assertSame('mandate-2', $this->repository->findActive(ContractSamples::tenant(), ContractSamples::employerNumber(1))?->id()->value());
    }
}
