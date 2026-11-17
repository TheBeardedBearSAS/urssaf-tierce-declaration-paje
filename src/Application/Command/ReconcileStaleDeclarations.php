<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command;

/**
 * Moves the declarations left submitting for too long to outcome_unknown (every tenant, ADR 0003).
 *
 * The limit is capped by the workflow policy (default page size when null).
 */
final readonly class ReconcileStaleDeclarations
{
    public function __construct(public ?int $limit) {}
}
