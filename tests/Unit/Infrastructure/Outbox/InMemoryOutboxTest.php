<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Outbox;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\OutboxEntry;
use TheBeardedBear\UrssafTierceDeclarationPaje\Contract\Event\DeclarationAlreadyDeclared;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Instant;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Outbox\InMemoryOutbox;

final class InMemoryOutboxTest extends TestCase
{
    public function testStartsEmpty(): void
    {
        $outbox = new InMemoryOutbox();

        static::assertSame([], $outbox->pending(10));
        static::assertSame(0, $outbox->purgeDispatchedBefore(self::at('2030-01-01T00:00:00Z')));
        static::assertNull($outbox->lastError(1));
    }

    public function testTheEventsArePendingInRecordingOrderWithASequence(): void
    {
        $outbox = new InMemoryOutbox();
        $first = self::event('e1');
        $second = self::event('e2');
        $third = self::event('e3');

        $outbox->append($first, $second);
        $outbox->append();
        $outbox->append($third);

        static::assertSame(
            [[1, $first, 0], [2, $second, 0], [3, $third, 0]],
            array_map(static fn(OutboxEntry $entry): array => [$entry->sequence, $entry->event, $entry->attempts], $outbox->pending(10)),
        );
    }

    public function testThePendingEventsAreLimited(): void
    {
        $outbox = new InMemoryOutbox();
        $outbox->append(self::event('e1'), self::event('e2'), self::event('e3'));

        static::assertSame([1, 2], array_map(static fn(OutboxEntry $entry): int => $entry->sequence, $outbox->pending(2)));
        static::assertSame([1], array_map(static fn(OutboxEntry $entry): int => $entry->sequence, $outbox->pending(1)));
    }

    public function testADispatchedEventIsNoLongerPending(): void
    {
        $outbox = new InMemoryOutbox();
        $outbox->append(self::event('e1'), self::event('e2'), self::event('e3'));
        [$first, $second] = $outbox->pending(2);

        $outbox->markDispatched($second, self::at('2026-11-15T10:00:00Z'));

        static::assertSame([1, 3], array_map(static fn(OutboxEntry $entry): int => $entry->sequence, $outbox->pending(10)));
        static::assertSame(1, $first->sequence);
    }

    public function testAFailedEventStaysPendingWithOneMoreAttemptAndItsError(): void
    {
        $outbox = new InMemoryOutbox();
        $outbox->append(self::event('e1'), self::event('e2'));
        [$first] = $outbox->pending(1);

        $outbox->markFailed($first, 'first failure');
        $outbox->markFailed($first, 'second failure');

        static::assertSame([[1, 2], [2, 0]], array_map(static fn(OutboxEntry $entry): array => [$entry->sequence, $entry->attempts], $outbox->pending(10)));
        static::assertSame('second failure', $outbox->lastError(1));
        static::assertNull($outbox->lastError(2));
    }

    public function testThePurgeDeletesTheEntriesDispatchedBeforeTheInstantOnly(): void
    {
        $outbox = new InMemoryOutbox();
        $outbox->append(self::event('e1'), self::event('e2'), self::event('e3'), self::event('e4'));
        [$first, $second, $third] = $outbox->pending(3);
        $outbox->markDispatched($first, self::at('2026-11-15T10:00:00Z'));
        $outbox->markDispatched($second, self::at('2026-11-15T11:00:00Z'));
        $outbox->markDispatched($third, self::at('2026-11-15T12:00:00Z'));

        static::assertSame(1, $outbox->purgeDispatchedBefore(self::at('2026-11-15T11:00:00Z')));
        static::assertSame(1, $outbox->purgeDispatchedBefore(self::at('2026-11-15T12:00:00Z')));
        static::assertSame(0, $outbox->purgeDispatchedBefore(self::at('2026-11-15T12:00:00Z')));
        static::assertSame(1, $outbox->purgeDispatchedBefore(self::at('2026-11-15T12:00:01Z')));
        static::assertSame([4], array_map(static fn(OutboxEntry $entry): int => $entry->sequence, $outbox->pending(10)));
    }

    public function testThePendingEventsAreNeverPurged(): void
    {
        $outbox = new InMemoryOutbox();
        $outbox->append(self::event('e1'));

        static::assertSame(0, $outbox->purgeDispatchedBefore(self::at('2030-01-01T00:00:00Z')));
        static::assertCount(1, $outbox->pending(10));
    }

    private static function event(string $id): DeclarationAlreadyDeclared
    {
        return new DeclarationAlreadyDeclared($id, '2026-11-15T10:00:00Z', 'tenant-1', 'record-1', 'declaration-1', 'account-1', 'childminder', '2026-10-01', '2026-10-31', ['ER_API_050_0070']);
    }

    private static function at(string $iso8601): Instant
    {
        return Instant::fromIso8601($iso8601);
    }
}
