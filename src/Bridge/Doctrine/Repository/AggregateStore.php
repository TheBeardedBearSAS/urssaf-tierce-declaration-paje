<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\Repository;

use Closure;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Stringable;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvariantViolation;

/**
 * The write protocol the three aggregate repositories share (ADR 0006, ADR 0014 decision 4): optimistic concurrency
 * and the unit of work.
 *
 * A save is refused with the "concurrent_modification" violation of the aggregate when another writer changed it since
 * it was read: a managed instance whose stored version is not its own (or whose row is gone), and an instance that is
 * not managed (never read, or detached by clear()) while its identifier is already stored. The refused instance is
 * detached, so the next read returns what is stored. The refusal is checked before anything is flushed, so the entity
 * manager stays usable: the ORM closes it only when a flush fails, which is left to the real race (another writer
 * between the check and the flush), where the native version column is the backstop.
 *
 * Reads: {@see self::find()} replaces a managed copy that another writer changed (or erased) by what is stored; the
 * queries of the repositories return the instance the entity manager already manages for a row, like every Doctrine
 * query.
 *
 * @template T of object
 *
 * @internal
 *
 * @mago-expect lint:too-many-methods
 */
final readonly class AggregateStore
{
    private const VERSION = 'version';

    /**
     * @param class-string<T>  $entity  the mapped aggregate class
     * @param non-empty-string $idType  the DBAL type of its identifier
     * @param non-empty-string $code    violation code of a stale save
     * @param non-empty-string $message violation message of a stale save
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
        private string $entity,
        private string $idType,
        private string $code,
        private string $message,
    ) {}

    /**
     * The aggregate of the identifier, null when nothing is stored: a managed copy that is no longer what is stored
     * (changed by another writer, or erased) is detached first, so that the caller reads the stored state.
     *
     * @return T|null
     */
    public function find(Stringable $id): ?object
    {
        $managed = $this->entityManager->getUnitOfWork()->tryGetById((string) $id, $this->entity);
        if ($managed !== false && !$this->isCurrent($managed, $id)) {
            $this->forget($managed);
        }

        return $this->entityManager->find($this->entity, $id);
    }

    /**
     * Persists and flushes the aggregate in one transaction, with the writes of $afterFlush (same connection).
     *
     * @param T                $aggregate
     * @param Closure(T): void $guard      throws the domain violation of a uniqueness rule broken by the aggregate: run
     *                                     once the instance is known current, then again to explain a unique constraint
     *                                     violation of the flush (a concurrent writer won the race since the first run)
     * @param Closure(T): void $afterFlush runs in the transaction, after the flush
     * @param-immediately-invoked-callable $guard
     * @param-immediately-invoked-callable $afterFlush
     *
     * @throws InvariantViolation the "concurrent_modification" violation, or the one $guard throws
     */
    public function save(object $aggregate, Stringable $id, Closure $guard, Closure $afterFlush): void
    {
        $this->assertCurrent($aggregate, $id);
        $guard($aggregate);
        try {
            $this->connection()->transactional(function () use ($aggregate, $afterFlush): void {
                $this->entityManager->persist($aggregate);
                $this->entityManager->flush();
                $afterFlush($aggregate);
            });
        } catch (UniqueConstraintViolationException $exception) {
            // The flush, or the writes after it, were rolled back with the transaction: the instance must not outlive it.
            $this->forget($aggregate);
            $guard($aggregate);
            if ($this->storedVersion($id) !== null) {
                // The same identifier was created by a concurrent writer.
                $this->refuse();
            }

            throw $exception;
        } catch (OptimisticLockException) {
            // The ORM closed the entity manager, which detached everything. The domain exception carries no previous
            // throwable: the Doctrine one only says that no row matched.
            $this->refuse();
        }
    }

    /**
     * Removes the stored aggregate (and its managed copy) immediately. A deferred EntityManager::remove() would run
     * after the inserts of the same flush, and break the supersede of a record by a new one in one transaction.
     */
    public function discard(Stringable $id): void
    {
        $this->detach($id);
        $this->entityManager
            ->createQuery('DELETE FROM ' . $this->entity . ' e WHERE e.id = :id')
            ->setParameter('id', $id, $this->idType)
            ->execute();
    }

    /**
     * Drops the managed copy of the identifier, if any: the next read returns what is stored.
     */
    public function detach(Stringable $id): void
    {
        $unitOfWork = $this->entityManager->getUnitOfWork();
        $managed = $unitOfWork->tryGetById((string) $id, $this->entity);
        if ($managed !== false) {
            $unitOfWork->detach($managed);
        }
    }

    /**
     * @return Connection the connection of the entity manager: the one the transaction manager and the outbox use too
     */
    public function connection(): Connection
    {
        return $this->entityManager->getConnection();
    }

    /**
     * @throws InvariantViolation always
     */
    private function refuse(): never
    {
        throw InvariantViolation::because($this->code, $this->message);
    }

    private function assertCurrent(object $aggregate, Stringable $id): void
    {
        if ($this->entityManager->contains($aggregate)) {
            if (!$this->isCurrent($aggregate, $id)) {
                $this->forget($aggregate);

                $this->refuse();
            }

            return;
        }
        if ($this->storedVersion($id) !== null) {
            $this->refuse();
        }
    }

    /**
     * Whether the managed instance still is what is stored: the stored version is its own (no row, or another version,
     * means that another writer saved, claimed or erased the aggregate since it was read).
     */
    private function isCurrent(object $managed, Stringable $id): bool
    {
        return $this->storedVersion($id) === $this->entityManager->getClassMetadata($this->entity)->getFieldValue($managed, self::VERSION);
    }

    /**
     * @return int|null the stored version, null when nothing is stored for the identifier
     */
    private function storedVersion(Stringable $id): ?int
    {
        /** @var list<int> $versions */
        $versions = $this->entityManager
            ->createQuery('SELECT e.version FROM ' . $this->entity . ' e WHERE e.id = :id')
            ->setParameter('id', $id, $this->idType)
            ->getSingleColumnResult();

        return $versions[0] ?? null;
    }

    private function forget(object $aggregate): void
    {
        $this->entityManager->getUnitOfWork()->detach($aggregate);
    }
}
