<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer;

/**
 * How an {@see EmployerAccount} came to be tracked (ADR 0006).
 */
enum EmployerOrigin: string
{
    /** Tracked by a use case of this package (e.g. a first verification or mandate registration). */
    case Tracked = 'tracked';

    /** Existing Pajemploi employer adopted by the platform. */
    case Imported = 'imported';
}
