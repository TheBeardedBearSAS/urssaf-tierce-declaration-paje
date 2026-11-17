<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Persistence\InMemory;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\Mandate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateState;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Persistence\InMemory\InMemoryMandateRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Doctrine\Contract\ContractSamples;

/**
 * What is specific to the in-memory repository (snapshots, orders on ties); the behaviour shared with the other
 * adapters is in the repository contract.
 */
final class InMemoryMandateRepositoryTest extends TestCase
{
    private InMemoryMandateRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new InMemoryMandateRepository();
    }

    public function testEveryReadReturnsANewCopy(): void
    {
        $mandate = ContractSamples::mandate(1);
        $this->repository->save($mandate);

        $first = $this->repository->get($mandate->id());

        static::assertNotSame($mandate, $first);
        static::assertNotSame($first, $this->repository->get($mandate->id()));
        static::assertNotSame($first, $this->repository->findActive($mandate->tenantId(), $mandate->employerNumber()));
        static::assertNotSame($first, $this->repository->findByEmployer($mandate->tenantId(), $mandate->employerNumber())[0]);
    }

    public function testAChangeIsOnlyVisibleOnceSaved(): void
    {
        $mandate = ContractSamples::mandate(1);
        $this->repository->save($mandate);
        $mandate->cancel(ContractSamples::at('2026-11-11T09:00:00Z'));

        static::assertSame(MandateState::Registered, $this->repository->get($mandate->id())->state());

        $this->repository->save($mandate);

        static::assertSame(MandateState::Cancelled, $this->repository->get($mandate->id())->state());
    }

    public function testAllReturnsCopiesInInsertionOrder(): void
    {
        $this->repository->save(ContractSamples::mandate(2));
        $this->repository->save(ContractSamples::mandate(1));

        $all = $this->repository->all();

        static::assertSame(['mandate-2', 'mandate-1'], array_map(static fn(Mandate $m): string => $m->id()->value(), $all));
        $all[0]->cancel(ContractSamples::at('2026-11-11T09:00:00Z'));
        static::assertSame(MandateState::Registered, $this->repository->get(MandateId::fromString('mandate-2'))->state());
    }

    public function testTheMandatesRegisteredAtTheSameInstantKeepTheirInsertionOrder(): void
    {
        $this->repository->save(ContractSamples::mandateOfEmployer(1, 3, MandateState::Cancelled));
        $this->repository->save(ContractSamples::mandateOfEmployer(1, 1, MandateState::Cancelled));
        $this->repository->save(ContractSamples::mandateOfEmployer(1, 2, MandateState::Registered));

        static::assertSame(
            ['mandate-3', 'mandate-1', 'mandate-2'],
            array_map(
                static fn(Mandate $m): string => $m->id()->value(),
                $this->repository->findByEmployer(ContractSamples::tenant(), ContractSamples::employerNumber(1)),
            ),
        );
    }

    public function testACancelledMandateCanBeSavedWhileAnotherIsActive(): void
    {
        $this->repository->save(ContractSamples::mandateOfEmployer(1, 1, MandateState::Registered));
        $this->repository->save(ContractSamples::mandateOfEmployer(1, 2, MandateState::Cancelled));

        static::assertCount(2, $this->repository->all());
    }
}
