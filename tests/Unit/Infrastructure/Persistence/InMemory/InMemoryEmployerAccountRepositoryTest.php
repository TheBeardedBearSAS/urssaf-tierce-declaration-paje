<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Persistence\InMemory;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerStage;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Persistence\InMemory\InMemoryEmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Doctrine\Contract\ContractSamples;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Employer\EmployerSamples;

/**
 * What is specific to the in-memory repository (snapshots, orders on ties); the behaviour shared with the other
 * adapters is in the repository contract.
 */
final class InMemoryEmployerAccountRepositoryTest extends TestCase
{
    private InMemoryEmployerAccountRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new InMemoryEmployerAccountRepository();
    }

    public function testEveryReadReturnsANewCopy(): void
    {
        $account = ContractSamples::account(1);
        $this->repository->save($account);

        $first = $this->repository->get($account->id());

        static::assertNotSame($account, $first);
        static::assertNotSame($first, $this->repository->get($account->id()));
        static::assertNotSame($first, $this->repository->findByEmployerNumber($account->tenantId(), $account->employerNumber()));
        static::assertNotSame($first, $this->repository->findByExternalReference($account->tenantId(), $account->externalReference()));
        static::assertNotSame($first, $this->repository->findLeastRecentlyChecked($account->tenantId(), 1)[0]);
    }

    public function testAChangeIsOnlyVisibleOnceSaved(): void
    {
        $account = ContractSamples::account(1);
        $this->repository->save($account);
        $account->markMandateRegistered(ContractSamples::at('2026-11-11T09:00:00Z'));

        static::assertSame(EmployerStage::Tracked, $this->repository->get($account->id())->stage());

        $this->repository->save($account);

        static::assertSame(EmployerStage::MandateRegistered, $this->repository->get($account->id())->stage());
    }

    public function testAllReturnsCopiesInInsertionOrder(): void
    {
        $this->repository->save(ContractSamples::account(2));
        $this->repository->save(ContractSamples::account(1));

        $all = $this->repository->all();

        static::assertSame(['account-2', 'account-1'], array_map(static fn(EmployerAccount $a): string => $a->id()->value(), $all));
        $all[0]->markMandateRegistered(ContractSamples::at('2026-11-11T09:00:00Z'));
        static::assertSame(EmployerStage::Tracked, $this->repository->get(EmployerAccountId::fromString('account-2'))->stage());
    }

    public function testTheAccountsNeverCheckedKeepTheirInsertionOrder(): void
    {
        $this->repository->save(ContractSamples::account(3));
        $this->repository->save(ContractSamples::account(1));
        $this->repository->save(ContractSamples::account(2));

        static::assertSame(
            ['account-3', 'account-1', 'account-2'],
            array_map(
                static fn(EmployerAccount $a): string => $a->id()->value(),
                $this->repository->findLeastRecentlyChecked(ContractSamples::tenant(), 10),
            ),
        );
    }

    public function testTheEmployerNumberIsCheckedBeforeTheExternalReference(): void
    {
        $this->repository->save(ContractSamples::account(1));
        $this->repository->save(ContractSamples::account(2));
        $bothDuplicated = EmployerAccount::track(
            EmployerAccountId::fromString('account-3'),
            ContractSamples::tenant(),
            ExternalReference::fromString('employer-1'),
            ContractSamples::employer(2),
            ContractSamples::at(),
        );

        try {
            $this->repository->save($bothDuplicated);
            static::fail('Expected a violation.');
        } catch (InvariantViolation $exception) {
            static::assertSame('employer_account.duplicate_employer_number', $exception->violationCode());
        }
    }

    public function testADuplicateOfAnotherTenantIsNotChecked(): void
    {
        $this->repository->save(ContractSamples::accountOfEmployer(1, 2, EmployerStage::Tracked, ContractSamples::OTHER_TENANT));

        $this->repository->save(ContractSamples::account(1));

        static::assertCount(2, $this->repository->all());
    }

    public function testTheTenantsAreSortedByIdWhateverTheInsertionOrder(): void
    {
        $this->repository->save(ContractSamples::account(1, EmployerStage::Tracked, 'tenant-c'));
        $this->repository->save(ContractSamples::account(2, EmployerStage::Tracked, 'tenant-a'));
        $this->repository->save(ContractSamples::account(3, EmployerStage::Tracked, 'tenant-b'));
        $this->repository->save(ContractSamples::account(4, EmployerStage::Tracked, 'tenant-a'));

        static::assertSame(
            ['tenant-a', 'tenant-b', 'tenant-c'],
            array_map(static fn(TenantId $tenant): string => $tenant->value(), $this->repository->tenants()),
        );
    }

    public function testTheSampleAccountOfTheDomainCanBeStored(): void
    {
        $this->repository->save(EmployerSamples::account());

        static::assertSame('account-1', $this->repository->get(EmployerSamples::accountId())->id()->value());
    }
}
