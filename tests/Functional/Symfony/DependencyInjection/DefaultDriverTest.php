<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\DependencyInjection\BundleConfiguration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\UrssafTierceDeclarationPajeBundle;

/**
 * The default persistence driver follows the installed ORM (ADR 0013), and "doctrine" is refused until its bridge ships.
 */
final class DefaultDriverTest extends TestCase
{
    public function testTheDriverIsDoctrineWhenTheOrmIsInstalled(): void
    {
        static::assertSame('doctrine', BundleConfiguration::defaultDriverFor(true));
    }

    public function testTheDriverIsInMemoryWithoutTheOrm(): void
    {
        static::assertSame('in_memory', BundleConfiguration::defaultDriverFor(false));
    }

    public function testTheRuntimeDefaultIsDoctrineBecauseTheOrmIsADevelopmentDependency(): void
    {
        static::assertSame('doctrine', BundleConfiguration::defaultDriver());
    }

    public function testTheDefaultDriverIsRefusedWhileTheDoctrineBridgeIsNotAvailable(): void
    {
        $extension = (new UrssafTierceDeclarationPajeBundle())->getContainerExtension();
        static::assertInstanceOf(ConfigurationExtensionInterface::class, $extension);
        $configuration = $extension->getConfiguration([], new ContainerBuilder());
        static::assertNotNull($configuration);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('set "persistence.driver" to "in_memory" or "custom"');

        // @mago-expect lint:no-literal-password Fake credentials of a test configuration.
        (new Processor())->processConfiguration($configuration, [[
            'environment' => 'sandbox',
            'credentials' => ['tenant-1' => ['client_id' => 'client-1', 'client_secret' => 'secret-1']],
        ]]);
    }
}
