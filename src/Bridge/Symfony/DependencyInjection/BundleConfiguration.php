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

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\ScalarNodeDefinition;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\ApiConfiguration;

/**
 * Configuration tree of the "urssaf_tierce_declaration_paje" bundle configuration (ADR 0013).
 *
 * The default persistence driver is "doctrine" when doctrine/orm is installed, "in_memory" otherwise; the "doctrine"
 * driver fails at build time without DoctrineBundle (ADR 0013 decision 3, ADR 0014), checked by the bundle while the
 * container is prepared.
 */
final class BundleConfiguration
{
    public const DRIVER_IN_MEMORY = 'in_memory';
    public const DRIVER_DOCTRINE = 'doctrine';
    public const DRIVER_CUSTOM = 'custom';

    /**
     * The default persistence driver: "doctrine" when doctrine/orm is installed.
     */
    public static function defaultDriver(): string
    {
        return self::defaultDriverFor(interface_exists(EntityManagerInterface::class));
    }

    /**
     * Pure predicate of {@see defaultDriver()}, so that both arms are tested whatever is installed.
     *
     * @mago-expect lint:no-boolean-flag-parameter The flag is the fact "doctrine/orm is installed", not a mode of the function.
     */
    public static function defaultDriverFor(bool $ormInstalled): string
    {
        return $ormInstalled ? self::DRIVER_DOCTRINE : self::DRIVER_IN_MEMORY;
    }

    public static function define(DefinitionConfigurator $definition): void
    {
        $root = $definition->rootNode();
        $root
            ->validate()
                ->ifTrue(ConfigurationRules::hasBothCredentialSources(...))
                ->thenInvalid('"credentials" and "credentials_provider" are mutually exclusive.')
            ->end()
            ->validate()
                ->ifTrue(ConfigurationRules::hasNoCredentialSource(...))
                ->thenInvalid('Configure either "credentials" or "credentials_provider".')
            ->end()
            ->validate()
                ->ifTrue(ConfigurationRules::lockEnabledWithoutComponent(...))
                ->thenInvalid('"lock.enabled: true" requires symfony/lock (composer require symfony/lock).')
            ->end()
            ->validate()
                ->ifTrue(ConfigurationRules::simulatedGatewayInProduction(...))
                ->thenInvalid('"gateway.simulated" is meant for tests and cannot be used with "environment: production".')
            ->end()
            ->validate()
                ->ifTrue(ConfigurationRules::outboxWithoutStorage(...))
                ->thenInvalid('The "custom" persistence driver requires "outbox.service" when the outbox is enabled.')
            ->end()
            ->validate()
                ->ifTrue(ConfigurationRules::schedulerEnabledWithoutComponent(...))
                ->thenInvalid('"scheduler.enabled" requires symfony/scheduler (composer require symfony/scheduler).')
            ->end()
            ->validate()
                ->ifTrue(ConfigurationRules::duplicateHeaderNames(...))
                ->thenInvalid('"request_id_header" and "application_id_header.name" must be different headers.')
            ->end()
            ->validate()
                ->ifTrue(ConfigurationRules::staleSubmissionNotAboveHttpTimeout(...))
                ->thenInvalid('"workflow.stale_submission_after" must be longer than "http_timeout": a declaration still being sent would be reconciled (ADR 0003, ADR 0006).')
            ->end()
            ->children()
                ->scalarNode('environment')
                    ->isRequired()
                    ->info('"sandbox" or "production"; usually "%env(URSSAF_PAJE_ENV)%" (resolved at runtime).')
                    ->validate()
                        ->ifTrue(ConfigurationRules::isUnknownEnvironment(...))
                        ->thenInvalid('Unknown environment %s: use "sandbox" or "production".')
                    ->end()
                ->end()
                ->scalarNode('base_uri')->defaultNull()->info('Overrides the base URI of the selected environment (sandbox host, proxy).')->end()
                ->scalarNode('user_agent')->defaultValue(ApiConfiguration::DEFAULT_USER_AGENT)->cannotBeEmpty()->info('User-Agent header of every call (visible ASCII).')->end()
                ->scalarNode('request_id_header')->defaultNull()->info('Name of the optional request-id header (its value is a UUID per attempt); null: not sent.')->end()
                ->arrayNode('application_id_header')
                    ->info('Optional fixed header sent on every call (name and value); both null: not sent.')
                    // A concrete array node cannot default to null: its children default to null instead.
                    ->addDefaultsIfNotSet()
                    ->validate()
                        ->ifTrue(ConfigurationRules::incompleteApplicationIdHeader(...))
                        ->thenInvalid('"application_id_header" needs both "name" and "value", or neither.')
                    ->end()
                    ->children()
                        ->scalarNode('name')->defaultNull()->end()
                        ->scalarNode('value')->defaultNull()->end()
                    ->end()
                ->end()
                ->integerNode('token_safety_margin')->min(0)->defaultValue(ApiConfiguration::DEFAULT_TOKEN_SAFETY_MARGIN_SECONDS)->info('Seconds before expiry from which an access token is renewed.')->end()
                ->arrayNode('retry_safe_writes')
                    ->info('Writes of unspecified idempotency the sandbox showed idempotent, retried like a read after a transport error or a 5xx: any of "023", "030", "031" (quote them in YAML, ADR 0003).')
                    ->scalarPrototype()
                        ->validate()
                            ->ifTrue(ConfigurationRules::isNotRetrySafeWriteCandidate(...))
                            ->thenInvalid('Unknown operation %s: only "023", "030" and "031" can be declared safe to retry (quote them in YAML).')
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('http_client')->defaultNull()->info('Service id of a PSR-18 client (default: a dedicated Symfony HttpClient).')->end()
                ->floatNode('http_timeout')->min(0.1)->defaultValue(15.0)->info('Timeout in seconds of the default HTTP client; also the value checked against "workflow.stale_submission_after" (state the timeout of your own client here when you give "http_client").')->end()
                ->scalarNode('credentials_provider')->defaultNull()->info('Service id of a CredentialsProvider.')->end()
                ->arrayNode('credentials')
                    ->info('Credentials by tenant id; use "%env(...)%" placeholders.')
                    ->useAttributeAsKey('tenant_id')
                    ->normalizeKeys(false)
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('client_id')->isRequired()->cannotBeEmpty()->end()
                            ->scalarNode('client_secret')->isRequired()->cannotBeEmpty()->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('tenants')
                    ->info('Tenants processed by commands that cannot discover them (default: the keys of "credentials").')
                    ->scalarPrototype()->cannotBeEmpty()->end()
                ->end()
                ->arrayNode('token_store')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('cache_pool')->defaultValue('cache.app')->cannotBeEmpty()->info('PSR-6 pool of the access tokens (a dedicated pool shared by every process is recommended).')->end()
                        ->scalarNode('service')->defaultNull()->info('Service id of a TokenStore (takes precedence over the cache pool).')->end()
                    ->end()
                ->end()
                ->arrayNode('gateway')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('simulated')->defaultFalse()->info('Tests only: replaces the URSSAF API with the in-memory Testing\\SimulatedPajeGateway (public service, to script the scenarios).')->end()
                    ->end()
                ->end()
                ->arrayNode('rate_limiter')
                    ->info('Local limiter per tenant and process (ADR 0011): off by default, URSSAF publishes no quota so no value is invented.')
                    ->addDefaultsIfNotSet()
                    ->validate()
                        ->ifTrue(ConfigurationRules::rateLimiterWithoutLimits(...))
                        ->thenInvalid('"rate_limiter.enabled" requires "rate_limiter.limit" and "rate_limiter.window".')
                    ->end()
                    ->children()
                        ->booleanNode('enabled')->defaultFalse()->info('Throttles the calls of a tenant in the current process.')->end()
                        ->integerNode('limit')->min(1)->defaultNull()->info('Calls per tenant within the window (required when enabled).')->end()
                        ->integerNode('window')->min(1)->defaultNull()->info('Window length in seconds (required when enabled).')->end()
                    ->end()
                ->end()
                ->arrayNode('lock')
                    ->info('Non-blocking Symfony Lock per command / handler ("urssaf_paje.<operation>.<scope>"): overlapping runs are skipped (command) or retried later (Messenger).')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->enumNode('enabled')->values(['auto', true, false])->defaultValue('auto')->info('"auto": enabled when symfony/lock is installed and the factory service exists.')->end()
                        ->scalarNode('factory')->defaultValue('lock.factory')->cannotBeEmpty()->info('Service id of a Symfony\\Component\\Lock\\LockFactory.')->end()
                        ->floatNode('ttl')->min(1)->defaultValue(3600.0)->info('Seconds after which a lock of a crashed process expires.')->end()
                    ->end()
                ->end()
                ->append(self::outbox())
                ->append(self::scheduler())
                ->append(self::workflow())
                ->append(self::persistence())
                ->arrayNode('messenger')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultFalse()->info('Registers the Messenger handlers of the employer status refresh messages.')->end()
                        ->scalarNode('bus')->defaultValue('messenger.default_bus')->cannotBeEmpty()->info('Bus of "urssaf:paje:employers:sync-status --async".')->end()
                    ->end()
                ->end()
            ->end();
    }

    private static function outbox(): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition('outbox');
        $node
            ->info('Integration events (Contract\\Event) written in the transaction of each state change, then relayed to the host (docs/integration-events.md).')
            ->addDefaultsIfNotSet()
            ->children()
                ->booleanNode('enabled')->defaultFalse()->end()
                ->scalarNode('service')->defaultNull()->info('Service id of your Outbox (required with the "custom" persistence driver; default: the in-memory outbox).')->end()
                ->scalarNode('sink')->defaultValue('messenger')->cannotBeEmpty()->info('"messenger" (bus below), "event_dispatcher", or the service id of an IntegrationEventSink.')->end()
                ->scalarNode('bus')->defaultValue('messenger.default_bus')->cannotBeEmpty()->info('Bus of the "messenger" sink.')->end()
                ->scalarNode('retention')->defaultValue(WorkflowPolicy::DEFAULT_OUTBOX_RETENTION)->info('How long a dispatched entry is kept (urssaf:paje:outbox:purge).')->validate()->ifTrue(ConfigurationRules::isInvalidInterval(...))->thenInvalid('Invalid ISO 8601 duration %s.')->end()->end()
            ->end();

        return $node;
    }

    private static function scheduler(): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition('scheduler');
        $node
            ->info('Symfony Scheduler schedule running the safety crons of the package (RunCommandMessage): an alternative to the crontab.')
            ->addDefaultsIfNotSet()
            ->children()
                ->booleanNode('enabled')->defaultFalse()->end()
                ->scalarNode('name')->defaultValue('urssaf_paje')->cannotBeEmpty()->info('Schedule name: consume it with "messenger:consume scheduler_<name>".')->end()
                ->arrayNode('frequencies')
                    ->addDefaultsIfNotSet()
                    ->append(self::frequency('sync_employer_statuses', 'P1D', 'urssaf:paje:employers:sync-status (020 is the only polling: cheap, not free)'))
                    ->append(self::frequency('reconcile_stale_declarations', 'PT15M', 'urssaf:paje:declarations:reconcile-stale'))
                    ->append(self::frequency('relay_outbox', 'PT1M', 'urssaf:paje:outbox:relay (only when the outbox is enabled)'))
                    ->append(self::frequency('purge_outbox', 'P1D', 'urssaf:paje:outbox:purge (only when the outbox is enabled)'))
                ->end()
            ->end();

        return $node;
    }

    private static function frequency(string $task, string $default, string $commands): ScalarNodeDefinition
    {
        $node = new ScalarNodeDefinition($task);
        $node
            ->defaultValue($default)
            ->info(\sprintf('ISO 8601 duration between two runs of %s; null to leave the task out.', $commands))
            ->validate()->ifTrue(ConfigurationRules::isInvalidOptionalInterval(...))->thenInvalid('Invalid ISO 8601 duration %s.')->end();

        return $node;
    }

    private static function workflow(): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition('workflow');
        $node
            ->addDefaultsIfNotSet()
            ->validate()
                ->ifTrue(ConfigurationRules::defaultPageAboveMax(...))
                ->thenInvalid('"default_page_size" must not be greater than "max_page_size".')
            ->end()
            ->children()
                ->scalarNode('business_timezone')
                    ->defaultValue(ApiConfiguration::DEFAULT_BUSINESS_TIME_ZONE)
                    ->info('Time zone of "today" for the period checks (French local time).')
                    ->validate()
                        ->ifTrue(ConfigurationRules::isUnknownTimeZone(...))
                        ->thenInvalid('Unknown time zone %s.')
                    ->end()
                ->end()
                ->scalarNode('stale_submission_after')
                    ->defaultValue(WorkflowPolicy::DEFAULT_STALE_SUBMISSION_AFTER)
                    ->info('Age after which a declaration left "submitting" is stale (moved to manual review, never resent). Must be longer than "http_timeout".')
                    ->validate()->ifTrue(ConfigurationRules::isInvalidInterval(...))->thenInvalid('Invalid ISO 8601 duration %s.')->end()
                ->end()
                ->integerNode('max_submission_attempts')->min(1)->defaultValue(WorkflowPolicy::DEFAULT_MAX_SUBMISSION_ATTEMPTS)->info('Attempts after which a technical failure is no longer requeued.')->end()
                ->booleanNode('require_local_mandate')->defaultFalse()->info('Refuses a declaration locally when no active mandate is tracked (ADR 0009; off by default: the mandate prerequisite is unknown).')->end()
                ->booleanNode('predeclare_before_declaring')->defaultFalse()->info('Runs the pre-declaration check (045 / 046) before every declaration (ADR 0008): one more call per declaration.')->end()
                ->integerNode('default_page_size')->min(1)->defaultValue(WorkflowPolicy::DEFAULT_PAGE_SIZE)->info('Records loaded by a batch use case without limit.')->end()
                ->integerNode('max_page_size')->min(1)->defaultValue(WorkflowPolicy::MAX_PAGE_SIZE)->info('Upper bound of a batch.')->end()
                ->arrayNode('erasure')
                    ->addDefaultsIfNotSet()
                    ->info('Defaults of the employer erasure (ForgetEmployer, urssaf:paje:employer:forget, ADR 0015); a submission in flight is always refused.')
                    ->children()
                        ->booleanNode('allow_unknown_outcomes')->defaultFalse()->info('Erases the declarations whose outcome is unknown instead of refusing.')->end()
                        ->booleanNode('allow_active_mandate')->defaultFalse()->info('Erases an employer whose mandate is still active (it stays active at URSSAF) instead of refusing.')->end()
                    ->end()
                ->end()
            ->end();

        return $node;
    }

    private static function persistence(): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition('persistence');
        $node
            ->addDefaultsIfNotSet()
            ->validate()
                ->ifTrue(ConfigurationRules::customDriverWithoutRepositories(...))
                ->thenInvalid('The "custom" driver requires "employer_account_repository", "mandate_repository" and "declaration_record_repository".')
            ->end()
            ->children()
                ->enumNode('driver')
                    ->values([self::DRIVER_IN_MEMORY, self::DRIVER_DOCTRINE, self::DRIVER_CUSTOM])
                    ->defaultValue(self::defaultDriver())
                    ->info('"doctrine" when doctrine/orm is installed, else "in_memory" (tests and prototypes).')
                ->end()
                ->scalarNode('employer_account_repository')->defaultNull()->info('Service id of your EmployerAccountRepository (driver "custom").')->end()
                ->scalarNode('mandate_repository')->defaultNull()->info('Service id of your MandateRepository (driver "custom").')->end()
                ->scalarNode('declaration_record_repository')->defaultNull()->info('Service id of your DeclarationRecordRepository (driver "custom").')->end()
                ->scalarNode('transaction_manager')->defaultNull()->info('Service id of your TransactionManager shared with the repositories (driver "custom"; default: ImmediateTransactionManager).')->end()
                ->scalarNode('workflow_statistics')->defaultNull()->info('Service id of your WorkflowStatistics (driver "custom", optional: without it, GetWorkflowOverviewHandler and urssaf:paje:status are not available).')->end()
                ->scalarNode('entity_manager')->defaultValue('default')->cannotBeEmpty()
                    ->info('Name of the Doctrine entity manager (driver "doctrine"). Use a DEDICATED entity manager (own connection) for this package: its repositories flush the whole unit of work of the entity manager they use, which would also write pending changes of the host entities sharing it.')
                ->end()
            ->end();

        return $node;
    }
}
