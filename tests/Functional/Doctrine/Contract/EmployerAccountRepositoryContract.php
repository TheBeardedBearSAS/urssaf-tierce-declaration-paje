<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Doctrine\Contract;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerOrigin;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerStage;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerVerification;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\OnlineAccountState;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;

/**
 * Behaviour every {@see EmployerAccountRepository} must have, independently of the storage. Identity-related behaviour
 * (copies versus managed instances) is deliberately not part of the contract. One method per scenario.
 *
 * @phpstan-require-extends TestCase
 * @require-extends TestCase
 * @mago-expect lint:too-many-methods
 */
trait EmployerAccountRepositoryContract
{
    protected EmployerAccountRepository $repository;

    abstract protected function createRepository(): EmployerAccountRepository;

    protected function setUp(): void
    {
        $this->repository = $this->createRepository();
    }

    /**
     * @param list<EmployerAccount> $accounts
     *
     * @return list<string>
     */
    protected static function accountIds(array $accounts): array
    {
        return array_map(static fn(EmployerAccount $account): string => $account->id()->value(), $accounts);
    }

    public function testASavedAccountIsReadBack(): void
    {
        $this->repository->save(ContractSamples::account(1, EmployerStage::MandateRegistered, ContractSamples::TENANT, '2026-11-10T09:00:00Z'));

        $read = $this->repository->get(EmployerAccountId::fromString('account-1'));

        static::assertSame('account-1', $read->id()->value());
        static::assertSame('tenant-1', $read->tenantId()->value());
        static::assertSame('employer-1', $read->externalReference()->value());
        static::assertTrue($read->employer()->equals(ContractSamples::employer(1)));
        static::assertSame(EmployerOrigin::Tracked, $read->origin());
        static::assertSame(EmployerStage::MandateRegistered, $read->stage());
        static::assertSame('2026-11-10T09:00:00Z', $read->createdAt()->toIso8601());
        static::assertSame('2026-11-10T09:00:00Z', $read->updatedAt()->toIso8601());
        static::assertNull($read->lastVerification());
        static::assertNull($read->lastCheckedAt());
    }

    public function testAnImportedOriginIsReadBack(): void
    {
        $account = EmployerAccount::import(
            EmployerAccountId::fromString('account-1'),
            ContractSamples::tenant(),
            ExternalReference::fromString('employer-1'),
            ContractSamples::employer(1),
            ContractSamples::at(),
        );
        $this->repository->save($account);

        static::assertSame(EmployerOrigin::Imported, $this->repository->get($account->id())->origin());
    }

    public function testTheEventsAreNeitherPersistedNorConsumedFromTheCaller(): void
    {
        $account = ContractSamples::pendingAccount(1);

        $this->repository->save($account);

        static::assertCount(1, $account->releaseEvents());
        static::assertSame([], $this->repository->get($account->id())->releaseEvents());
    }

    public function testASavedVerificationIsReadBackWithoutChangingTheStage(): void
    {
        $account = ContractSamples::account(1);
        $this->repository->save($account);
        $verification = ContractSamples::verification(true, 'inconnu-demain');
        $checkedAt = ContractSamples::at('2026-11-12T08:30:00Z');
        $stored = $this->repository->get($account->id());
        $stored->applyVerification($verification, $checkedAt);

        $this->repository->save($stored);
        $read = $this->repository->get($account->id());

        static::assertSame(EmployerStage::Tracked, $read->stage());
        $lastVerification = $read->lastVerification();
        static::assertInstanceOf(EmployerVerification::class, $lastVerification);
        static::assertTrue($lastVerification->authorised());
        static::assertSame('inconnu-demain', $lastVerification->state()->raw());
        static::assertSame('2026-11-12T08:30:00Z', $read->lastCheckedAt()?->toIso8601());
        static::assertSame('2026-11-12T08:30:00Z', $read->updatedAt()->toIso8601());
        static::assertSame('2026-11-10T09:00:00Z', $read->createdAt()->toIso8601());
    }

    public function testASavedStageChangeIsReadBack(): void
    {
        $account = ContractSamples::account(1);
        $this->repository->save($account);
        $stored = $this->repository->get($account->id());
        $stored->markMandateRegistered(ContractSamples::at('2026-11-11T09:00:00Z'));
        $this->repository->save($stored);
        $stored->markMandateCancelled(ContractSamples::at('2026-11-12T09:00:00Z'));
        $this->repository->save($stored);

        static::assertSame(EmployerStage::MandateCancelled, $this->repository->get($account->id())->stage());
    }

    public function testAVerificationOfAnUnauthorisedEmployerIsReadBack(): void
    {
        $account = ContractSamples::account(1);
        $account->applyVerification(EmployerVerification::of(false, OnlineAccountState::of('Inconnu')), ContractSamples::at('2026-11-12T08:30:00Z'));
        $this->repository->save($account);

        $lastVerification = $this->repository->get($account->id())->lastVerification();

        static::assertInstanceOf(EmployerVerification::class, $lastVerification);
        static::assertFalse($lastVerification->authorised());
        static::assertSame('Inconnu', $lastVerification->state()->raw());
    }

    public function testAnUnknownAccountIsNotFound(): void
    {
        try {
            $this->repository->get(EmployerAccountId::fromString('missing'));
            static::fail('Expected RecordNotFound.');
        } catch (RecordNotFound $exception) {
            static::assertSame('employer_account.not_found', $exception->violationCode());
            static::assertSame('missing', $exception->identifier());
        }
    }

    public function testTheSameEmployerNumberOfAnotherAccountOfTheTenantIsRefused(): void
    {
        $this->repository->save(ContractSamples::account(1));
        $duplicate = EmployerAccount::track(
            EmployerAccountId::fromString('account-2'),
            ContractSamples::tenant(),
            ExternalReference::fromString('employer-2'),
            ContractSamples::employer(1),
            ContractSamples::at(),
        );

        try {
            $this->repository->save($duplicate);
            static::fail('Expected a violation.');
        } catch (InvariantViolation $exception) {
            static::assertSame('employer_account.duplicate_employer_number', $exception->violationCode());
            // The employer number is credential-like (ADR 0007): never in an exception.
            static::assertSame([], $exception->params());
        }
    }

    public function testTheSameExternalReferenceOfAnotherAccountOfTheTenantIsRefused(): void
    {
        $this->repository->save(ContractSamples::account(1));
        $duplicate = EmployerAccount::track(
            EmployerAccountId::fromString('account-2'),
            ContractSamples::tenant(),
            ExternalReference::fromString('employer-1'),
            ContractSamples::employer(2),
            ContractSamples::at(),
        );

        try {
            $this->repository->save($duplicate);
            static::fail('Expected a violation.');
        } catch (InvariantViolation $exception) {
            static::assertSame('employer_account.duplicate_external_reference', $exception->violationCode());
            static::assertSame(['external_reference' => 'employer-1'], $exception->params());
        }
    }

    public function testTheSameEmployerNumberAndExternalReferenceInAnotherTenantAreAccepted(): void
    {
        $this->repository->save(ContractSamples::account(1));
        $this->repository->save(ContractSamples::accountOfEmployer(1, 2, EmployerStage::Tracked, ContractSamples::OTHER_TENANT));

        static::assertSame(
            'tenant-2',
            $this->repository->findByEmployerNumber(ContractSamples::tenant(ContractSamples::OTHER_TENANT), ContractSamples::employerNumber(1))?->tenantId()->value(),
        );
    }

    public function testSavingAnExistingAccountAgainIsAnUpdateNotADuplicate(): void
    {
        $account = ContractSamples::account(1);
        $this->repository->save($account);
        $this->repository->save($account);

        static::assertSame(['account-1'], self::accountIds($this->repository->findLeastRecentlyChecked(ContractSamples::tenant(), 10)));
    }

    public function testTheFindersAreTenantScopedAndReturnNullWhenAbsent(): void
    {
        $this->repository->save(ContractSamples::account(1));
        $tenant = ContractSamples::tenant();
        $other = ContractSamples::tenant(ContractSamples::OTHER_TENANT);

        static::assertSame('account-1', $this->repository->findByEmployerNumber($tenant, ContractSamples::employerNumber(1))?->id()->value());
        static::assertSame('account-1', $this->repository->findByExternalReference($tenant, ExternalReference::fromString('employer-1'))?->id()->value());
        static::assertNull($this->repository->findByEmployerNumber($other, ContractSamples::employerNumber(1)));
        static::assertNull($this->repository->findByExternalReference($other, ExternalReference::fromString('employer-1')));
        static::assertNull($this->repository->findByEmployerNumber($tenant, ContractSamples::employerNumber(2)));
        static::assertNull($this->repository->findByExternalReference($tenant, ExternalReference::fromString('employer-2')));
    }

    public function testTheLeastRecentlyCheckedComeNeverCheckedFirstThenAscending(): void
    {
        $this->repository->save(ContractSamples::checkedAccount(1, '2026-11-12T10:00:00Z'));
        $this->repository->save(ContractSamples::checkedAccount(2, '2026-11-11T10:00:00Z'));
        $this->repository->save(ContractSamples::account(3, EmployerStage::Tracked, ContractSamples::TENANT, ContractSamples::AT));
        $this->repository->save(ContractSamples::account(4, EmployerStage::Tracked, ContractSamples::OTHER_TENANT));
        $tenant = ContractSamples::tenant();

        static::assertSame(['account-3', 'account-2', 'account-1'], self::accountIds($this->repository->findLeastRecentlyChecked($tenant, 10)));
        static::assertSame(['account-3', 'account-2'], self::accountIds($this->repository->findLeastRecentlyChecked($tenant, 2)));
        static::assertSame(['account-4'], self::accountIds($this->repository->findLeastRecentlyChecked(ContractSamples::tenant(ContractSamples::OTHER_TENANT), 10)));
        static::assertSame([], $this->repository->findLeastRecentlyChecked(ContractSamples::tenant('tenant-9'), 10));
    }

    public function testTheTenantsAreDistinctAndSorted(): void
    {
        static::assertSame([], $this->repository->tenants());

        $this->repository->save(ContractSamples::account(1, EmployerStage::Tracked, 'tenant-b'));
        $this->repository->save(ContractSamples::account(2, EmployerStage::Tracked, 'tenant-a'));
        $this->repository->save(ContractSamples::account(3, EmployerStage::Tracked, 'tenant-b'));

        static::assertSame(
            ['tenant-a', 'tenant-b'],
            array_map(static fn(TenantId $tenant): string => $tenant->value(), $this->repository->tenants()),
        );
    }

    public function testADeletedAccountIsGoneAndDeletingAMissingOneIsANoOp(): void
    {
        $this->repository->save(ContractSamples::account(1));
        $this->repository->save(ContractSamples::account(2));

        $this->repository->delete(EmployerAccountId::fromString('account-1'));
        $this->repository->delete(EmployerAccountId::fromString('missing'));

        static::assertNull($this->repository->findByExternalReference(ContractSamples::tenant(), ExternalReference::fromString('employer-1')));
        static::assertSame(['account-2'], self::accountIds($this->repository->findLeastRecentlyChecked(ContractSamples::tenant(), 10)));
        $this->expectException(RecordNotFound::class);
        $this->repository->get(EmployerAccountId::fromString('account-1'));
    }
}
