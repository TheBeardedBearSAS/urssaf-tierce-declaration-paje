<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto;

/**
 * What a use case did with one record (batch items and declaration results). ADR 0003, ADR 0006.
 */
enum ItemOutcome: string
{
    /** Accepted by URSSAF (methods 050 / 051). */
    case Declared = 'declared';

    /** Refused by URSSAF with business errors (final, nothing recorded by URSSAF). */
    case Rejected = 'rejected';

    /** URSSAF already holds this declaration (duplicate): final, counted as needing attention (ADR 0003). */
    case AlreadyDeclared = 'already_declared';

    /** The call may have reached URSSAF without a usable answer: never resent, to be resolved manually. */
    case OutcomeUnknown = 'outcome_unknown';

    /** URSSAF refused the tenant credentials; see RequeueAfterInvalidCredentials. */
    case InvalidCredentials = 'invalid_credentials';

    /** Failed technically once more after the maximum number of attempts: left in error, to be handled manually. */
    case Exhausted = 'exhausted';

    /** Not pending any more (e.g. claimed by another worker or in flight): left untouched, no call made. */
    case NotClaimed = 'not_claimed';

    /** The same declaration was already recorded: its current state is returned, no call made. */
    case Replayed = 'replayed';

    /** A stale submission was marked outcome unknown: an operator must check it. */
    case ManualReview = 'manual_review';

    /** The URSSAF status of an employer changed (method 020). */
    case Changed = 'changed';

    /** Method 020 answered with no change for the employer. */
    case Unchanged = 'unchanged';

    /** URSSAF refused the query for this record (codes attached): left untouched. */
    case Failed = 'failed';

    /** A temporary failure (429, transport, 5xx, local rate limit): left untouched, to be retried later. */
    case RetryLater = 'retry_later';
}
