<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto;

/**
 * Outcome of one outbox relay run (ADR 0012).
 */
final readonly class RelayReport
{
    /**
     * @param int $dispatched events delivered and marked as dispatched
     * @param int $failed     events whose delivery failed (kept pending)
     * @param int $deferred   events left pending because an earlier event of the same aggregate failed (order kept)
     */
    public function __construct(public int $dispatched, public int $failed, public int $deferred) {}
}
