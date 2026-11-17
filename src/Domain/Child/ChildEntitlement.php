<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Child;

/**
 * Documented values of `reponseStatutEnfant` (api-reference §3.1).
 */
enum ChildEntitlement: int
{
    /** Entitled child not found, or not entitled. */
    case NotEntitled = 0;

    /** Entitled child found. */
    case Entitled = 1;

    /** Entitled child found through a partial search. */
    case EntitledPartialMatch = 2;
}
