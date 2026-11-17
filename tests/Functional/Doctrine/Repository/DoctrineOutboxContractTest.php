<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Doctrine\Repository;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Outbox;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service\IgnoredFailures;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service\RecordedEvents;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service\UnitOfWork;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\DoctrineTransactionManager;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Repository\DoctrineOutbox;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Event\NullEventPublisher;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Doctrine\Contract\ContractSamples;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Doctrine\Contract\OutboxContract;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Doctrine\Support\TestDatabase;

/**
 * The shared outbox contract on an in-memory SQLite database, and the Doctrine-specific scenarios: the events are
 * written with the state change or not at all, and the failure and the dispatch are kept in their columns.
 */
final class DoctrineOutboxContractTest extends TestCase
{
    use OutboxContract;

    protected function createOutbox(): Outbox
    {
        return new DoctrineOutbox(TestDatabase::entityManager());
    }

    public function testTheEventsAreWrittenWithTheStateChangeOrNotAtAll(): void
    {
        $entityManager = TestDatabase::entityManager();
        $outbox = new DoctrineOutbox($entityManager);
        $unitOfWork = self::unitOfWork($entityManager, $outbox);

        $unitOfWork->run(static fn(RecordedEvents $events) => $events->record(...ContractSamples::pendingMandate(1)->releaseEvents()));
        try {
            $unitOfWork->run(static function (RecordedEvents $events): never {
                $events->record(...ContractSamples::pendingMandate(2)->releaseEvents());

                throw new RuntimeException('Rolled back.');
            });
        } catch (RuntimeException $failure) {
            static::assertSame('Rolled back.', $failure->getMessage());
        }

        $pending = $outbox->pending(10);
        static::assertCount(1, $pending);
        static::assertSame('mandate-1', $pending[0]->event->aggregateId);
        $row = $entityManager->getConnection()->fetchAssociative('SELECT * FROM urssaf_paje_outbox');
        static::assertIsArray($row);
        static::assertEquals(
            [$pending[0]->event->eventId, $pending[0]->event->type(), 1, $pending[0]->event->tenantId, $pending[0]->event->aggregateId, $pending[0]->event->occurredAt, null, 0, null],
            [$row['event_id'], $row['type'], $row['version'], $row['tenant_id'], $row['aggregate_id'], $row['occurred_at'], $row['dispatched_at'], $row['attempts'], $row['last_error']],
        );
        static::assertIsString($row['payload']);
        static::assertSame($pending[0]->event->toArray(), json_decode($row['payload'], true, 512, \JSON_THROW_ON_ERROR));
    }

    public function testTheLastFailureAndTheDispatchInstantAreKept(): void
    {
        $entityManager = TestDatabase::entityManager();
        $outbox = new DoctrineOutbox($entityManager);
        self::unitOfWork($entityManager, $outbox)->run(static fn(RecordedEvents $events) => $events->record(...ContractSamples::pendingMandate(1)->releaseEvents()));

        $outbox->markFailed($outbox->pending(1)[0], 'Broker down.');
        $outbox->markDispatched($outbox->pending(1)[0], ContractSamples::at('2026-11-15T10:00:00Z'));

        static::assertSame('Broker down.', $entityManager->getConnection()->fetchOne('SELECT last_error FROM urssaf_paje_outbox'));
        static::assertSame('2026-11-15 10:00:00', $entityManager->getConnection()->fetchOne('SELECT dispatched_at FROM urssaf_paje_outbox'));
    }

    private static function unitOfWork(EntityManagerInterface $entityManager, Outbox $outbox): UnitOfWork
    {
        return new UnitOfWork(new DoctrineTransactionManager($entityManager), new NullEventPublisher(), new IgnoredFailures(), $outbox);
    }
}
