<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\DependencyInjection;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use LogicException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\DoctrineIntegration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\UrssafTierceDeclarationPajeBundle;

/**
 * How the bundle reads the raw configurations to prepend the Doctrine mapping (ADR 0013 decision 3, ADR 0014): the last
 * configuration that sets an option wins, whatever the other ones leave out.
 */
final class BundlePrependTest extends TestCase
{
    public function testTheLastConfigurationThatSetsTheDriverWins(): void
    {
        $container = self::withDoctrine([['persistence' => ['driver' => 'doctrine']], ['persistence' => ['driver' => 'in_memory']]]);

        self::prepend($container);

        static::assertSame([], $container->getExtensionConfig('doctrine'));
    }

    public function testAnOptionLeftOutByALaterConfigurationKeepsItsValue(): void
    {
        $container = self::withDoctrine([
            ['persistence' => ['driver' => 'doctrine', 'entity_manager' => 'urssaf']],
            ['persistence' => ['driver' => 'doctrine']],
            ['persistence' => []],
            [],
        ]);

        self::prepend($container);

        $expected = DoctrineIntegration::doctrineBundleConfiguration();
        $mappings = $expected['orm']['mappings'];
        unset($expected['orm']['mappings']);
        $expected['orm']['entity_managers'] = ['urssaf' => ['mappings' => $mappings]];
        static::assertSame([$expected], $container->getExtensionConfig('doctrine'));
    }

    public function testTheDefaultEntityManagerIsMappedAtTheTopOfTheOrmConfiguration(): void
    {
        $container = self::withDoctrine([['persistence' => ['driver' => 'doctrine', 'entity_manager' => 'default']]]);

        self::prepend($container);

        static::assertSame([DoctrineIntegration::doctrineBundleConfiguration()], $container->getExtensionConfig('doctrine'));
    }

    public function testTheDriverIsDoctrineByDefaultWhenTheOrmIsInstalled(): void
    {
        $container = self::withDoctrine([['persistence' => null], ['persistence' => ['driver' => 42, 'entity_manager' => ['no']]]]);

        self::prepend($container);

        static::assertSame([DoctrineIntegration::doctrineBundleConfiguration()], $container->getExtensionConfig('doctrine'));
    }

    public function testNothingIsPrependedWithoutDoctrineBundleWhenAnotherDriverIsSelected(): void
    {
        $container = self::withoutDoctrine([['persistence' => ['driver' => 'custom']]]);

        self::prepend($container);

        static::assertSame([], $container->getExtensionConfig('doctrine'));
    }

    public function testTheDoctrineDriverFailsWithoutDoctrineBundle(): void
    {
        $container = self::withoutDoctrine([['persistence' => ['driver' => 'doctrine']]]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The "doctrine" persistence driver requires DoctrineBundle (doctrine/doctrine-bundle): register it, or set "persistence.driver" to "in_memory" or "custom".');

        self::prepend($container);
    }

    public function testTheDefaultDriverFailsWithoutDoctrineBundleWhenTheOrmIsInstalled(): void
    {
        $container = self::withoutDoctrine([[]]);

        $this->expectException(LogicException::class);

        self::prepend($container);
    }

    /**
     * @param list<array<string, mixed>> $configs raw configurations of the bundle, in merge order
     */
    private static function withDoctrine(array $configs): ContainerBuilder
    {
        $container = self::withoutDoctrine($configs);
        $doctrine = (new DoctrineBundle())->getContainerExtension();
        static::assertNotNull($doctrine);
        $container->registerExtension($doctrine);

        return $container;
    }

    /**
     * @param list<array<string, mixed>> $configs raw configurations of the bundle, in merge order
     */
    private static function withoutDoctrine(array $configs): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $extension = (new UrssafTierceDeclarationPajeBundle())->getContainerExtension();
        static::assertNotNull($extension);
        $container->registerExtension($extension);
        foreach ($configs as $config) {
            $container->loadFromExtension(UrssafTierceDeclarationPajeBundle::ALIAS, $config);
        }

        return $container;
    }

    private static function prepend(ContainerBuilder $container): void
    {
        $instanceof = [];
        (new UrssafTierceDeclarationPajeBundle())->prependExtension(
            new ContainerConfigurator($container, new PhpFileLoader($container, new FileLocator()), $instanceof, __FILE__, __FILE__),
            $container,
        );
    }
}
