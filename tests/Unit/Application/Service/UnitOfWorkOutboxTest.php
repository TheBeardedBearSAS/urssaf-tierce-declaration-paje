<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Service;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\OutboxEntry;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service\IgnoredFailures;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service\IgnoredOutbox;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service\RecordedEvents;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service\UnitOfWork;
use TheBeardedBear\UrssafTierceDeclarationPaje\Contract\Event\DeclarationDeclared;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Instant;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Event\NullEventPublisher;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Fake\DomainEventSamples;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Fake\Journal;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Fake\JournalingOutbox;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Fake\RecordingTransactionManager;

final class UnitOfWorkOutboxTest extends TestCase
{
    public function testTheIntegrationEventsAreAppendedInsideTheTransactionAfterTheOperation(): void
    {
        $journal = new Journal();
        $outbox = new JournalingOutbox($journal);
        $unitOfWork = new UnitOfWork(
            new RecordingTransactionManager($journal),
            new NullEventPublisher(),
            new IgnoredFailures(),
            $outbox,
        );

        $result = $unitOfWork->run(static function (RecordedEvents $events) use ($journal): string {
            $journal->add('work');
            $events->record(DomainEventSamples::declarationDeclared(), DomainEventSamples::declarationRequeued(), DomainEventSamples::mandateRegistered());

            return 'done';
        });

        static::assertSame('done', $result);
        static::assertSame(
            ['begin', 'work', 'append:urssaf.paje.declaration.declared,urssaf.paje.mandate.registered', 'commit'],
            $journal->entries(),
        );
        $declared = $outbox->appended()[0] ?? null;
        static::assertInstanceOf(DeclarationDeclared::class, $declared);
        static::assertSame('account-1', $declared->employerAccountId);
    }

    public function testMandateAndEmployerStatusEventsAreTranslatedWithoutAnyLookup(): void
    {
        $journal = new Journal();
        $outbox = new JournalingOutbox($journal);
        $unitOfWork = new UnitOfWork(new RecordingTransactionManager($journal), new NullEventPublisher(), new IgnoredFailures(), $outbox);

        $unitOfWork->run(static function (RecordedEvents $events): void {
            $events->record(
                DomainEventSamples::mandateRegistered(),
                DomainEventSamples::mandateCancelled(),
                DomainEventSamples::employerStatusRefreshed(),
            );
        });

        static::assertSame(
            ['begin', 'append:urssaf.paje.mandate.registered,urssaf.paje.mandate.cancelled,urssaf.paje.employer.status_changed', 'commit'],
            $journal->entries(),
        );
    }

    public function testTheDefaultConstructorRunsMandateAndStatusEventsWithoutFailing(): void
    {
        $journal = new Journal();
        $unitOfWork = new UnitOfWork(new RecordingTransactionManager($journal), new NullEventPublisher());

        $result = $unitOfWork->run(static function (RecordedEvents $events): string {
            $events->record(DomainEventSamples::mandateRegistered(), DomainEventSamples::employerStatusRefreshed(), DomainEventSamples::declarationAlreadyDeclared());

            return 'done';
        });

        static::assertSame('done', $result);
        static::assertSame(['begin', 'commit'], $journal->entries());
    }

    public function testNothingIsAppendedWhenTheOperationFails(): void
    {
        $journal = new Journal();
        $unitOfWork = new UnitOfWork(new RecordingTransactionManager($journal), new NullEventPublisher(), new IgnoredFailures(), new JournalingOutbox($journal));

        $this->expectException(RuntimeException::class);

        try {
            $unitOfWork->run(static function (RecordedEvents $events): never {
                $events->record(DomainEventSamples::declarationDeclared());

                throw new RuntimeException('failure');
            });
        } finally {
            static::assertSame(['begin', 'failed'], $journal->entries());
        }
    }

    public function testAnOperationWithoutIntegrationEventsAppendsAnEmptyBatch(): void
    {
        $journal = new Journal();
        $unitOfWork = new UnitOfWork(new RecordingTransactionManager($journal), new NullEventPublisher(), new IgnoredFailures(), new JournalingOutbox($journal));

        $unitOfWork->run(static function (RecordedEvents $events): void {
            $events->record(DomainEventSamples::declarationRecorded());
        });

        static::assertSame(['begin', 'append:', 'commit'], $journal->entries());
    }

    public function testTheDefaultOutboxRecordsNothing(): void
    {
        $outbox = new IgnoredOutbox();
        $unitOfWork = new UnitOfWork(new RecordingTransactionManager(new Journal()), new NullEventPublisher());

        $unitOfWork->run(static function (RecordedEvents $events): void {
            $events->record(DomainEventSamples::declarationRecorded());
        });
        $entry = new OutboxEntry(1, self::integrationEvent(), 0);
        $outbox->markDispatched($entry, Instant::fromIso8601('2026-11-15T10:00:00Z'));
        $outbox->markFailed($entry, 'error');

        static::assertSame([], $outbox->pending(10));
        static::assertSame(0, $outbox->purgeDispatchedBefore(Instant::fromIso8601('2026-11-15T10:00:00Z')));
    }

    private static function integrationEvent(): DeclarationDeclared
    {
        return new DeclarationDeclared('event-1', '2026-11-15T10:00:00Z', 'tenant-1', 'record-1', 'declaration-1', null, 'childminder', '2026-10-01', '2026-10-31', 'REF', []);
    }
}
