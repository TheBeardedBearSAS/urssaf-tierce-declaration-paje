<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\DependencyInjection\BundleConfiguration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\DependencyInjection\ServiceIds;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\DependencyInjection\ServiceRegistrar;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Diagnostic\InstallationCheck;

/**
 * Symfony integration of the URSSAF "API Tierce Déclaration PAJE" client (Symfony 6.4 and 7.x), ADR 0013.
 *
 * @phpstan-import-type Config from ServiceRegistrar
 */
final class UrssafTierceDeclarationPajeBundle extends AbstractBundle
{
    public const ALIAS = 'urssaf_tierce_declaration_paje';
    public const PARAMETER_TENANTS = ServiceIds::PARAMETER_TENANTS;

    protected string $extensionAlias = self::ALIAS;

    /**
     * The bundle directory ({@see AbstractBundle} assumes a class one level below it), so that the framework finds the
     * shipped translations in Resources/translations.
     */
    public function getPath(): string
    {
        return __DIR__;
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        BundleConfiguration::define($definition);
    }

    /**
     * @param array<array-key, mixed> $config the configuration validated by {@see BundleConfiguration}
     */
    public function loadExtension(array $config, ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        /** @var Config $validated */
        $validated = $config;
        $tenants = $validated['tenants'] !== [] ? $validated['tenants'] : array_map(strval(...), array_keys($validated['credentials']));
        $configurator->parameters()->set(ServiceIds::PARAMETER_TENANTS, $tenants);
        (new ServiceRegistrar($configurator->services()))->register($validated);
        $container->registerForAutoconfiguration(InstallationCheck::class)->addTag(ServiceIds::TAG_INSTALLATION_CHECK);
    }
}
