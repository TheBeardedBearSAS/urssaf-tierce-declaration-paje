<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\DependencyInjection;

/**
 * Identifiers of the services of the bundle that are not named after a class or an interface, and the names of the tags
 * (ADR 0013: everything is prefixed "urssaf_tierce_declaration_paje", nothing is shared with the other URSSAF packages).
 *
 * The other services have the id of their class or port ({@see \TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Clock}...).
 */
final class ServiceIds
{
    public const SYMFONY_HTTP_CLIENT = 'urssaf_tierce_declaration_paje.http_client';
    public const PSR18_CLIENT = 'urssaf_tierce_declaration_paje.psr18_client';
    public const HTTP_TRANSPORT = 'urssaf_tierce_declaration_paje.http_transport';
    public const API_CONFIGURATION = 'urssaf_tierce_declaration_paje.api_configuration';
    public const GATEWAY_OPTIONS = 'urssaf_tierce_declaration_paje.gateway_options';
    public const TOKEN_PROVIDER = 'urssaf_tierce_declaration_paje.token_provider';

    /** Token provider of urssaf:paje:check: its own in-memory store, so that every check asks URSSAF. */
    public const PROBE_TOKEN_PROVIDER = 'urssaf_tierce_declaration_paje.probe_token_provider';
    public const JSON_CLIENT = 'urssaf_tierce_declaration_paje.json_client';

    /** Tag of the installation checks of "urssaf:paje:check" (priority: report order). */
    public const TAG_INSTALLATION_CHECK = 'urssaf_tierce_declaration_paje.installation_check';

    /** Container parameter: the tenants processed by the commands that cannot discover them (list of strings). */
    public const PARAMETER_TENANTS = 'urssaf_tierce_declaration_paje.tenants';

    /** Monolog channel of the logs of the package (never personal data, ADR 0007). */
    public const LOGGER_CHANNEL = 'urssaf_paje';

    /**
     * Service id of a DoctrineBundle entity manager (driver "doctrine", option "persistence.entity_manager").
     */
    public static function entityManager(string $name): string
    {
        return \sprintf('doctrine.orm.%s_entity_manager', $name);
    }
}
