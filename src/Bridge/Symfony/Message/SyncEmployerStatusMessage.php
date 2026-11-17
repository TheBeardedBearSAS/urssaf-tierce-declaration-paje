<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Message;

/**
 * Asks to refresh (method 020) one tracked employer, e.g. dispatched with a DelayStamp after the mandate notification (030).
 * Holds identifiers only (no personal data, ADR 0007); safe to retry, it is a read (ADR 0013, decision 2).
 */
final readonly class SyncEmployerStatusMessage
{
    public function __construct(public string $tenantId, public string $employerAccountId) {}
}
