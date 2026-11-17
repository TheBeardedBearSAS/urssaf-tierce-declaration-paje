<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Diagnostic;

/**
 * Outcome of one installation check; only a failure makes `urssaf:paje:check` exit with 1.
 */
enum CheckStatus: string
{
    case Ok = 'ok';
    case Warning = 'warning';
    case Failure = 'failure';
    case Skipped = 'skipped';
}
