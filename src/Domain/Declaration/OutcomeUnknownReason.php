<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration;

/**
 * Why the outcome of a submission is unknown (ADR 0003).
 */
enum OutcomeUnknownReason: string
{
    /** Transport error, 5xx or unreadable answer on 050 / 051: the request may have reached URSSAF. */
    case MayHaveReachedUrssaf = 'may_have_reached_urssaf';

    /** Left in state "submitting" past the stale submission delay. */
    case StaleSubmission = 'stale_submission';
}
