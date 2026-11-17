<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony;

use LogicException;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\DoctrineIntegration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\DependencyInjection\BundleConfiguration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\DependencyInjection\ConfigurationRules;
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
     * Maps the aggregates with Doctrine when the "doctrine" persistence driver is selected (the default when the ORM is
     * installed). Fails fast when DoctrineBundle is not registered: this method receives the real container, whereas
     * {@see loadExtension()} receives a per-extension container where no other extension is visible (ADR 0013 decision 3,
     * ADR 0014).
     *
     * @throws LogicException the "doctrine" driver is selected and DoctrineBundle is not registered
     */
    public function prependExtension(ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        $persistence = self::selectedPersistence($container->getExtensionConfig(self::ALIAS));
        if (ConfigurationRules::doctrineDriverWithoutBundle($persistence['driver'], $container->hasExtension('doctrine'))) {
            throw new LogicException('The "doctrine" persistence driver requires DoctrineBundle (doctrine/doctrine-bundle): register it, or set "persistence.driver" to "in_memory" or "custom".');
        }
        if (!$container->hasExtension('doctrine') || $persistence['driver'] !== BundleConfiguration::DRIVER_DOCTRINE) {
            return;
        }
        $doctrine = DoctrineIntegration::doctrineBundleConfiguration();
        if ($persistence['entity_manager'] !== 'default') {
            $mappings = $doctrine['orm']['mappings'];
            unset($doctrine['orm']['mappings']);
            $doctrine['orm']['entity_managers'] = [$persistence['entity_manager'] => ['mappings' => $mappings]];
        }
        $container->prependExtensionConfig('doctrine', $doctrine);
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

    /**
     * @param array<array-key, array<array-key, mixed>> $configs raw configurations of the bundle, in merge order
     *
     * @return array{driver: string, entity_manager: string}
     */
    private static function selectedPersistence(array $configs): array
    {
        $selected = ['driver' => BundleConfiguration::defaultDriver(), 'entity_manager' => 'default'];
        foreach ($configs as $config) {
            $persistence = \is_array($config['persistence'] ?? null) ? $config['persistence'] : [];
            $selected['driver'] = self::stringOr($persistence['driver'] ?? null, $selected['driver']);
            $selected['entity_manager'] = self::stringOr($persistence['entity_manager'] ?? null, $selected['entity_manager']);
        }

        return $selected;
    }

    private static function stringOr(mixed $value, string $default): string
    {
        return \is_string($value) ? $value : $default;
    }
}
