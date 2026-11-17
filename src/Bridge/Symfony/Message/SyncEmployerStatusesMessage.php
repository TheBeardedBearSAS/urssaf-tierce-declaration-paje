<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Message;

/**
 * Asks to refresh (method 020) the least recently checked employers of one tenant. Holds an identifier only (no personal
 * data, ADR 0007); safe to retry, it is a read (ADR 0013, decision 2).
 */
final readonly class SyncEmployerStatusesMessage
{
    public function __construct(public string $tenantId) {}
}
