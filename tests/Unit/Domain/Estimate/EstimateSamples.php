<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Estimate;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\PostalCode;

/**
 * Builders shared by the estimate request tests.
 */
final class EstimateSamples
{
    public static function postalCode(string $code = '94022'): PostalCode
    {
        return PostalCode::fromString($code);
    }
}
