<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Query;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\CalculationMode;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Estimate\ChildminderEstimateRequest;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Estimate\HomeCareEstimateRequest;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Estimate\YoungestChildAge;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Amount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\PostalCode;

/**
 * Valid estimate requests for the query tests.
 */
final class EstimateFixtures
{
    private function __construct() {}

    public static function homeCare(): HomeCareEstimateRequest
    {
        return HomeCareEstimateRequest::of(
            PostalCode::fromString('94022'),
            null,
            Amount::fromDecimalString('1500'),
            CalculationMode::flatRate(),
            120,
            null,
            null,
            YoungestChildAge::threeToSix(),
            false,
        );
    }

    public static function childminder(): ChildminderEstimateRequest
    {
        return ChildminderEstimateRequest::of(null, PostalCode::fromString('75001'), Amount::fromDecimalString('900'), 1, 20, null, null, null);
    }
}
