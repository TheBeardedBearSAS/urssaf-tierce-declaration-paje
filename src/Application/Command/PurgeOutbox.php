<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command;

use DateInterval;

/**
 * Deletes the outbox entries dispatched for longer than the retention (the policy "outboxRetention" when null).
 */
final readonly class PurgeOutbox
{
    public function __construct(public ?DateInterval $retention) {}
}
