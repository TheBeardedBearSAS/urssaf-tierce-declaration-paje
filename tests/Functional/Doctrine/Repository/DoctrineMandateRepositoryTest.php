<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Doctrine\Repository;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\DoctrineTransactionManager;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Repository\DoctrineMandateRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateRepository;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateState;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Doctrine\Contract\ContractSamples;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Doctrine\Contract\MandateRepositoryContract;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Doctrine\Support\BeforeNextFlush;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Doctrine\Support\FileDatabase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Doctrine\Support\TestDatabase;
use Throwable;

/**
 * The shared contract and the Doctrine-specific scenarios of the repository, on an in-memory SQLite database. The other
 * writer is a second entity manager over the same connection. One method per scenario.
 *
 * @mago-expect lint:too-many-methods
 */
final class DoctrineMandateRepositoryTest extends TestCase
{
    use MandateRepositoryContract {
        setUp as private contractSetUp;
    }

    private EntityManagerInterface $entityManager;

    private ?MandateRepository $other = null;

    private ?FileDatabase $file = null;

    protected function setUp(): void
    {
        $this->entityManager = TestDatabase::entityManager();
        $this->contractSetUp();
    }

    protected function createRepository(): MandateRepository
    {
        return new DoctrineMandateRepository($this->entityManager);
    }

    protected function anotherWriter(): MandateRepository
    {
        return $this->other ??= new DoctrineMandateRepository(TestDatabase::anotherEntityManager($this->entityManager));
    }

    protected function tearDown(): void
    {
        $this->file?->delete();
    }

    /**
     * @phpstan-impure
     *
     * @return list<array<string, mixed>>
     */
    private static function keys(EntityManagerInterface $entityManager): array
    {
        return $entityManager->getConnection()->fetchAllAssociative(
            'SELECT tenant_id, employer_number, mandate_id FROM urssaf_paje_active_mandates ORDER BY mandate_id',
        );
    }

    public function testItReturnsTheManagedInstanceWhileItIsCurrent(): void
    {
        $mandate = ContractSamples::mandate(1);
        $this->repository->save($mandate);
        $id = MandateId::fromString('mandate-1');

        static::assertSame($mandate, $this->repository->get($id));
        static::assertSame($mandate, $this->repository->get($id));

        $this->entityManager->clear();
        $reloaded = $this->repository->get($id);

        static::assertNotSame($mandate, $reloaded);
        static::assertSame([], $reloaded->releaseEvents());
        static::assertSame($reloaded, $this->repository->get($id));
    }

    public function testEveryStateButCancelledIsActive(): void
    {
        // The queries of the repository select the active mandates as "state <> cancelled".
        foreach (MandateState::cases() as $state) {
            static::assertSame($state !== MandateState::Cancelled, $state->isActive());
        }
    }

    public function testDeletingAMandateForgetsItsManagedCopy(): void
    {
        $mandate = ContractSamples::mandate(1);
        $this->repository->save($mandate);

        $this->repository->delete($mandate->id());

        static::assertFalse($this->entityManager->contains($mandate));
    }

    public function testTheKeyRowFollowsEverySave(): void
    {
        $mandate = ContractSamples::mandate(1);

        static::assertSame([], self::keys($this->entityManager));

        $this->repository->save($mandate);
        $registered = self::keys($this->entityManager);
        $this->repository->save($mandate);
        $again = self::keys($this->entityManager);
        $mandate->cancel(ContractSamples::at('2026-11-12T09:00:00Z'));
        $this->repository->save($mandate);
        $cancelled = self::keys($this->entityManager);
        $this->repository->save(ContractSamples::mandateOfEmployer(1, 2));
        $renewed = self::keys($this->entityManager);

        static::assertSame([['tenant_id' => 'tenant-1', 'employer_number' => ContractSamples::employerNumber(1)->value(), 'mandate_id' => 'mandate-1']], $registered);
        static::assertSame($registered, $again);
        static::assertSame([], $cancelled);
        static::assertSame([['tenant_id' => 'tenant-1', 'employer_number' => ContractSamples::employerNumber(1)->value(), 'mandate_id' => 'mandate-2']], $renewed);
    }

    public function testDeletingAMandateRemovesItsKeyRowAndKeepsTheOthers(): void
    {
        $this->repository->save(ContractSamples::mandateOfEmployer(1, 1));
        $this->repository->save(ContractSamples::mandateOfEmployer(2, 2));

        $this->repository->delete(MandateId::fromString('mandate-1'));

        static::assertSame([['tenant_id' => 'tenant-1', 'employer_number' => ContractSamples::employerNumber(2)->value(), 'mandate_id' => 'mandate-2']], self::keys($this->entityManager));
    }

    public function testADeleteInsideATransactionThatThenFailsKeepsTheMandateAndItsKeyRow(): void
    {
        $this->repository->save(ContractSamples::mandate(1));
        $keys = self::keys($this->entityManager);

        try {
            (new DoctrineTransactionManager($this->entityManager))->transactional(function (): never {
                $this->repository->delete(MandateId::fromString('mandate-1'));

                throw new LogicException('boom');
            });
        } catch (LogicException) {
            static::addToAssertionCount(1);
        }

        static::assertSame($keys, self::keys($this->entityManager));
        static::assertSame(['mandate-1'], $this->entityManager->getConnection()->fetchFirstColumn('SELECT id FROM urssaf_paje_mandates'));
    }

    public function testAnInstanceThatIsNotManagedWhileItsIdentifierIsStoredIsRefusedAndNothingIsOverwritten(): void
    {
        $mandate = ContractSamples::mandate(1);
        $this->repository->save($mandate);
        $this->entityManager->clear();
        $mandate->cancel(ContractSamples::at('2026-11-12T09:00:00Z'));

        foreach ([$mandate, ContractSamples::mandate(1)] as $unmanaged) {
            try {
                $this->repository->save($unmanaged);
                static::fail('Expected a violation.');
            } catch (InvariantViolation $exception) {
                static::assertSame('mandate.concurrent_modification', $exception->violationCode());
            }
        }

        static::assertTrue($this->entityManager->isOpen());
        static::assertSame(MandateState::Registered, $this->repository->get(MandateId::fromString('mandate-1'))->state());
        static::assertCount(1, self::keys($this->entityManager));
    }

    public function testAStaleCopyIsRefusedBeforeAnyFlushAndForgotten(): void
    {
        $this->repository->save(ContractSamples::mandate(1));
        $id = MandateId::fromString('mandate-1');
        $stale = $this->repository->get($id);
        $other = $this->anotherWriter()->get($id);
        $other->cancel(ContractSamples::at('2026-11-12T09:00:00Z'));
        $this->anotherWriter()->save($other);
        $stale->cancel(ContractSamples::at('2026-11-13T09:00:00Z'));
        $flushed = false;
        BeforeNextFlush::run($this->entityManager, static function () use (&$flushed): void {
            $flushed = true;
        });

        try {
            $this->repository->save($stale);
            static::fail('Expected a violation.');
        } catch (InvariantViolation $exception) {
            static::assertSame('mandate.concurrent_modification', $exception->violationCode());
            static::assertSame([], $exception->params());
        }

        static::assertFalse($flushed);
        static::assertTrue($this->entityManager->isOpen());
        static::assertFalse($this->entityManager->contains($stale));
        static::assertSame('2026-11-12T09:00:00Z', $this->repository->get($id)->cancelledAt()?->toIso8601());
    }

    public function testAReadReplacesAManagedCopyThatAnotherWriterChanged(): void
    {
        $this->repository->save(ContractSamples::mandate(1));
        $id = MandateId::fromString('mandate-1');
        $managed = $this->repository->get($id);
        $other = $this->anotherWriter()->get($id);
        $other->cancel(ContractSamples::at('2026-11-12T09:00:00Z'));
        $this->anotherWriter()->save($other);

        $fresh = $this->repository->get($id);

        static::assertNotSame($managed, $fresh);
        static::assertSame(MandateState::Cancelled, $fresh->state());
        static::assertSame($fresh, $this->repository->get($id));
    }

    public function testTheMandatesOfAnEmployerAreListedByRegistrationThenIdentifierWhateverTheInsertionOrder(): void
    {
        foreach ([3, 1, 2] as $number) {
            $this->repository->save(ContractSamples::mandateOfEmployer(1, $number, MandateState::Cancelled, ContractSamples::TENANT, '2026-11-10T09:00:00Z'));
        }
        $this->repository->save(ContractSamples::mandateOfEmployer(1, 9, MandateState::Cancelled, ContractSamples::TENANT, '2026-11-01T09:00:00Z'));

        static::assertSame(
            ['mandate-9', 'mandate-1', 'mandate-2', 'mandate-3'],
            self::mandateIds($this->repository->findByEmployer(ContractSamples::tenant(), ContractSamples::employerNumber(1))),
        );
    }

    public function testTheActiveMandateIsTheEarliestRegisteredThenTheLowestIdentifierWhenARepairedDatabaseHoldsSeveral(): void
    {
        $this->repository->save(ContractSamples::mandateOfEmployer(1, 9, MandateState::Cancelled));
        $this->repository->save(ContractSamples::mandateOfEmployer(1, 1, MandateState::Cancelled));
        $this->repository->save(ContractSamples::mandateOfEmployer(1, 5, MandateState::Cancelled, ContractSamples::TENANT, '2026-11-20T09:00:00Z'));
        $this->entityManager->getConnection()->executeStatement("UPDATE urssaf_paje_mandates SET state = 'registered'");
        $this->entityManager->clear();

        static::assertSame('mandate-1', $this->repository->findActive(ContractSamples::tenant(), ContractSamples::employerNumber(1))?->id()->value());
    }

    public function testACancelledMandateIsSavedAgainWhileAnotherOneIsActive(): void
    {
        $cancelled = ContractSamples::mandate(1);
        $this->repository->save($cancelled);
        $cancelled->cancel(ContractSamples::at('2026-11-11T09:00:00Z'));
        $this->repository->save($cancelled);
        $this->repository->save(ContractSamples::mandateOfEmployer(1, 2));

        $this->repository->save($cancelled);

        static::assertSame(['mandate-2'], array_column(self::keys($this->entityManager), 'mandate_id'));
    }

    public function testARefusedSecondActiveMandateIsRefusedBeforeAnyFlush(): void
    {
        $this->repository->save(ContractSamples::mandate(1));
        $flushed = false;
        BeforeNextFlush::run($this->entityManager, static function () use (&$flushed): void {
            $flushed = true;
        });

        try {
            $this->repository->save(ContractSamples::mandateOfEmployer(1, 2));
            static::fail('Expected a violation.');
        } catch (InvariantViolation $exception) {
            static::assertSame('mandate.duplicate_active', $exception->violationCode());
        }

        static::assertFalse($flushed);
    }

    public function testAConcurrentSecondActiveMandateIsReportedAsAViolationAndForgotten(): void
    {
        $this->file = new FileDatabase();
        $entityManager = $this->file->entityManager();
        $repository = new DoctrineMandateRepository($entityManager);
        $rival = new DoctrineMandateRepository($this->file->entityManager());
        BeforeNextFlush::run($entityManager, static function () use ($rival): void {
            $rival->save(ContractSamples::mandateOfEmployer(1, 2));
        });
        $mandate = ContractSamples::mandateOfEmployer(1, 1);

        try {
            $repository->save($mandate);
            static::fail('Expected a violation.');
        } catch (InvariantViolation $exception) {
            static::assertSame('mandate.duplicate_active', $exception->violationCode());
        }

        static::assertTrue($entityManager->isOpen());
        static::assertFalse($entityManager->contains($mandate));
        $stored = $this->file->entityManager();
        static::assertSame(['mandate-2'], array_column(self::keys($stored), 'mandate_id'));
        static::assertSame(1, $stored->getConnection()->fetchOne('SELECT COUNT(*) FROM urssaf_paje_mandates'));
    }

    public function testTheSameIdentifierCreatedConcurrentlyIsReportedAsAConcurrentModification(): void
    {
        $this->file = new FileDatabase();
        $entityManager = $this->file->entityManager();
        $repository = new DoctrineMandateRepository($entityManager);
        $rival = new DoctrineMandateRepository($this->file->entityManager());
        BeforeNextFlush::run($entityManager, static function () use ($rival): void {
            $rival->save(ContractSamples::mandateOfEmployer(2, 1));
        });

        try {
            $repository->save(ContractSamples::mandateOfEmployer(1, 1));
            static::fail('Expected a violation.');
        } catch (InvariantViolation $exception) {
            static::assertSame('mandate.concurrent_modification', $exception->violationCode());
        }

        static::assertSame(
            [ContractSamples::employerNumber(2)->value()],
            $this->file->entityManager()->getConnection()->fetchFirstColumn('SELECT employer_number FROM urssaf_paje_mandates'),
        );
    }

    public function testAKeyRowThatNoMandateExplainsIsRethrownUnchanged(): void
    {
        $this->entityManager->getConnection()->insert('urssaf_paje_active_mandates', ['tenant_id' => 'tenant-1', 'employer_number' => ContractSamples::employerNumber(1)->value(), 'mandate_id' => 'ghost']);
        $mandate = ContractSamples::mandate(1);

        $raised = false;
        try {
            $this->repository->save($mandate);
        } catch (Throwable $failure) {
            $raised = true;
            static::assertInstanceOf(UniqueConstraintViolationException::class, $failure);
        }

        static::assertTrue($raised);
        static::assertTrue($this->entityManager->isOpen());
        static::assertFalse($this->entityManager->contains($mandate));
        static::assertSame([], $this->entityManager->getConnection()->fetchFirstColumn('SELECT id FROM urssaf_paje_mandates'));
    }

    public function testACopyChangedBetweenTheCheckAndTheFlushIsRefusedByTheVersionColumn(): void
    {
        $this->file = new FileDatabase();
        $rivalRepository = new DoctrineMandateRepository($this->file->entityManager());
        $rivalRepository->save(ContractSamples::mandate(1));
        $id = MandateId::fromString('mandate-1');
        $entityManager = $this->file->entityManager();
        $repository = new DoctrineMandateRepository($entityManager);
        $managed = $repository->get($id);
        $managed->cancel(ContractSamples::at('2026-11-13T09:00:00Z'));
        BeforeNextFlush::run($entityManager, static function () use ($rivalRepository, $id): void {
            $other = $rivalRepository->get($id);
            $other->cancel(ContractSamples::at('2026-11-12T09:00:00Z'));
            $rivalRepository->save($other);
        });

        try {
            $repository->save($managed);
            static::fail('Expected a violation.');
        } catch (InvariantViolation $exception) {
            static::assertSame('mandate.concurrent_modification', $exception->violationCode());
        }

        static::assertSame('2026-11-12T09:00:00Z', (new DoctrineMandateRepository($this->file->entityManager()))->get($id)->cancelledAt()?->toIso8601());
    }
}
