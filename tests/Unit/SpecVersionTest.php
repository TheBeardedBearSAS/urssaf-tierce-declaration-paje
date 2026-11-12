<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\SpecVersion;

final class SpecVersionTest extends TestCase
{
    public function testItExposesTheImplementedSpecificationVersion(): void
    {
        static::assertMatchesRegularExpression('/^1\.5\.2$/', SpecVersion::CURRENT);
    }
}
