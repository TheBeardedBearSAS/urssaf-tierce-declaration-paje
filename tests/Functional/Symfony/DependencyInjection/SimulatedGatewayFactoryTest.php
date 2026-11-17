<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\DependencyInjection;

use LogicException;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\DependencyInjection\SimulatedGatewayFactory;
use ValueError;

/**
 * The runtime guard of the "gateway.simulated" rule.
 */
final class SimulatedGatewayFactoryTest extends TestCase
{
    public function testTheSandboxGetsTheSimulator(): void
    {
        static::assertSame([], SimulatedGatewayFactory::create('sandbox')->calls());
    }

    public function testProductionIsRefused(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('"gateway.simulated" is meant for tests and cannot be used with the production environment.');

        SimulatedGatewayFactory::create('production');
    }

    public function testAnUnknownEnvironmentIsRefused(): void
    {
        $this->expectException(ValueError::class);

        SimulatedGatewayFactory::create('staging');
    }
}
