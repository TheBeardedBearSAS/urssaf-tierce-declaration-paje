<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Fake;

use Closure;
use Override;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\TransactionManager;
use Throwable;

/**
 * Transactional fake: a failed operation really is rolled back. The state of the stores (the in-memory repositories and
 * the outbox of the test) is captured when the transaction begins and put back when the operation throws, like a
 * database would. Journals "begin", "commit" and "rollback".
 *
 * Use it wherever a test claims that "nothing is recorded" or "nothing is requeued"; the contrast is
 * ImmediateTransactionManager (no rollback at all) and {@see RecordingTransactionManager} (journal only).
 *
 * Another writer is not part of the transaction: what it commits survives the rollback. A test that makes another
 * writer act in the middle of a transaction does it with {@see self::outside()}.
 */
final class SnapshottingTransactionManager implements TransactionManager
{
    /** @var list<object> */
    private readonly array $stores;

    private ?StoreSnapshots $committedState = null;

    private int $committed = 0;

    public function __construct(
        private readonly Journal $journal,
        object ...$stores,
    ) {
        $this->stores = array_values($stores);
    }

    /**
     * @template T
     *
     * @param callable(): T $operation
     *
     * @param-immediately-invoked-callable $operation
     *
     * @return T
     */
    #[Override]
    public function transactional(callable $operation): mixed
    {
        $this->journal->add('begin');
        $this->committedState = StoreSnapshots::of(...$this->stores);

        try {
            $result = $operation();
        } catch (Throwable $exception) {
            $this->committedState->restore();
            $this->committedState = null;
            $this->journal->add('rollback');

            throw $exception;
        }
        $this->committedState = null;
        $this->journal->add('commit');
        ++$this->committed;

        return $result;
    }

    /**
     * Another writer commits its changes in the middle of the running transaction: they are applied to the stores and
     * to the committed state, so that a rollback of the transaction keeps them. Outside a transaction it just runs.
     *
     * @param Closure(): void $writes
     */
    public function outside(Closure $writes): void
    {
        $committed = $this->committedState;
        if (!$committed instanceof StoreSnapshots) {
            $writes();

            return;
        }
        $inTransaction = StoreSnapshots::of(...$this->stores);
        $committed->restore();

        try {
            $writes();
        } finally {
            $after = StoreSnapshots::of(...$this->stores);
            $this->committedState = $after;
            $inTransaction->restore();
        }
        $committed->mergeChangesUntil($after);
    }

    public function committed(): int
    {
        return $this->committed;
    }
}
