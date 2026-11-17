<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Environment;

final class Stacks
{
    public static function tenant(): TenantId
    {
        return TenantId::fromString('tenant-1');
    }

    public static function sandbox(): Stack
    {
        return new Stack(ApiConfiguration::forEnvironment(Environment::Sandbox), 0);
    }

    public static function production(): Stack
    {
        return new Stack(ApiConfiguration::forEnvironment(Environment::Production), 0);
    }

    public static function configured(ApiConfiguration $configuration): Stack
    {
        return new Stack($configuration, 0);
    }

    /**
     * Sandbox stack whose clock advances by the given number of microseconds at every now() call.
     */
    public static function stepping(int $stepMicroseconds): Stack
    {
        return new Stack(ApiConfiguration::forEnvironment(Environment::Sandbox), $stepMicroseconds);
    }
}
