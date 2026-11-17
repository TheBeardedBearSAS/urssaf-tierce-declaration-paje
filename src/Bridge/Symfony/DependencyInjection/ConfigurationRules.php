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

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\OperationRetryClass;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Environment;
use Throwable;

/**
 * Validation predicates of the configuration tree (each one is true when the value is invalid).
 *
 * One small predicate per rule of the tree, hence the method count and the total complexity.
 *
 * @mago-expect lint:too-many-methods
 * @mago-expect lint:cyclomatic-complexity
 */
final class ConfigurationRules
{
    /**
     * @param array<array-key, mixed> $value
     */
    public static function hasBothCredentialSources(array $value): bool
    {
        /** @var array{credentials: array<array-key, mixed>, credentials_provider: ?string} $config */
        $config = $value;

        return $config['credentials'] !== [] && $config['credentials_provider'] !== null;
    }

    /**
     * @param array<array-key, mixed> $value
     */
    public static function hasNoCredentialSource(array $value): bool
    {
        /** @var array{credentials: array<array-key, mixed>, credentials_provider: ?string} $config */
        $config = $value;

        return $config['credentials'] === [] && $config['credentials_provider'] === null;
    }

    /**
     * @param array<array-key, mixed> $value
     */
    public static function simulatedGatewayInProduction(array $value): bool
    {
        /** @var array{environment: string, gateway: array{simulated: bool}} $config */
        $config = $value;

        return $config['gateway']['simulated'] && $config['environment'] === Environment::Production->value;
    }

    /**
     * @param array<array-key, mixed> $value
     */
    public static function lockEnabledWithoutComponent(array $value): bool
    {
        return self::lockRequiredButMissing($value, class_exists(LockFactory::class));
    }

    /**
     * @param array<array-key, mixed> $value
     */
    public static function lockRequiredButMissing(array $value, bool $componentInstalled): bool
    {
        /** @var array{lock: array{enabled: bool|string}} $config */
        $config = $value;

        return $config['lock']['enabled'] === true && !$componentInstalled;
    }

    /**
     * @param array<array-key, mixed> $value
     */
    public static function schedulerEnabledWithoutComponent(array $value): bool
    {
        return self::schedulerRequiredButMissing($value, interface_exists(ScheduleProviderInterface::class));
    }

    /**
     * @param array<array-key, mixed> $value
     */
    public static function schedulerRequiredButMissing(array $value, bool $componentInstalled): bool
    {
        /** @var array{scheduler: array{enabled: bool}} $config */
        $config = $value;

        return $config['scheduler']['enabled'] && !$componentInstalled;
    }

    /**
     * @param array<array-key, mixed> $value
     */
    public static function outboxWithoutStorage(array $value): bool
    {
        /** @var array{outbox: array{enabled: bool, service: ?string}, persistence: array{driver: string}} $config */
        $config = $value;

        return $config['outbox']['enabled'] && $config['outbox']['service'] === null
            && $config['persistence']['driver'] === BundleConfiguration::DRIVER_CUSTOM;
    }

    /**
     * Mirrors the "duplicate header" check of ApiConfiguration, caught at build time (header names are case
     * insensitive).
     *
     * @param array<array-key, mixed> $value
     */
    public static function duplicateHeaderNames(array $value): bool
    {
        /** @var array{request_id_header: ?string, application_id_header: array{name: ?string}} $config */
        $config = $value;
        $requestId = $config['request_id_header'];
        $applicationId = $config['application_id_header']['name'];

        return $requestId !== null && $applicationId !== null && strtolower($requestId) === strtolower($applicationId);
    }

    /**
     * @param array<array-key, mixed> $value
     */
    public static function incompleteApplicationIdHeader(array $value): bool
    {
        /** @var array{name: ?string, value: ?string} $header */
        $header = $value;

        return ($header['name'] === null || $header['name'] === '') !== ($header['value'] === null || $header['value'] === '');
    }

    /**
     * @param array<array-key, mixed> $value
     */
    public static function defaultPageAboveMax(array $value): bool
    {
        /** @var array{default_page_size: int, max_page_size: int} $workflow */
        $workflow = $value;

        return $workflow['default_page_size'] > $workflow['max_page_size'];
    }

    /**
     * ADR 0006 / ADR 0003: a record left "submitting" for longer than "workflow.stale_submission_after" is reconciled
     * (moved to "outcome_unknown"), so a threshold that does not exceed the HTTP timeout would reconcile calls that are
     * still in flight. The threshold is measured by adding it to a fixed instant (1 January 2026, UTC), so that "P1M"
     * counts 31 days.
     *
     * An "http_timeout" that is not a number (an "%env(float:...)%" placeholder, resolved at runtime) cannot be compared
     * at build time: the rule is skipped.
     *
     * @param array<array-key, mixed> $value
     */
    public static function staleSubmissionNotAboveHttpTimeout(array $value): bool
    {
        /** @var array{http_timeout: mixed, workflow: array{stale_submission_after: string}} $config */
        $config = $value;
        // @mago-expect analysis:mixed-assignment An environment placeholder is a string while the container is built.
        $timeout = $config['http_timeout'];
        if (!\is_int($timeout) && !\is_float($timeout)) {
            return false;
        }

        return self::seconds(new DateInterval($config['workflow']['stale_submission_after'])) <= $timeout;
    }

    /**
     * The "doctrine" driver needs DoctrineBundle (its entity manager services), checked by the bundle while the
     * container is prepared (the per-extension container used when loading cannot tell whether a bundle is registered),
     * ADR 0013 decision 3.
     */
    public static function doctrineDriverWithoutBundle(string $driver, bool $bundleRegistered): bool
    {
        return $driver === BundleConfiguration::DRIVER_DOCTRINE && !$bundleRegistered;
    }

    /**
     * @param array<array-key, mixed> $value
     */
    public static function customDriverWithoutRepositories(array $value): bool
    {
        /** @var array{driver: string, employer_account_repository: ?string, mandate_repository: ?string, declaration_record_repository: ?string} $persistence */
        $persistence = $value;

        return $persistence['driver'] === BundleConfiguration::DRIVER_CUSTOM
            && ($persistence['employer_account_repository'] === null
                || $persistence['mandate_repository'] === null
                || $persistence['declaration_record_repository'] === null);
    }

    /**
     * ADR 0011: no quota value is invented, the host gives the limit and the window of the local limiter.
     *
     * @param array<array-key, mixed> $value
     */
    public static function rateLimiterWithoutLimits(array $value): bool
    {
        /** @var array{enabled: bool, limit: ?int, window: ?int} $rateLimiter */
        $rateLimiter = $value;

        return $rateLimiter['enabled'] && ($rateLimiter['limit'] === null || $rateLimiter['window'] === null);
    }

    /**
     * The writes of unspecified idempotency (023, 030, 031) are the only ones that can be declared safe to retry
     * (ADR 0003); mirrors the check of ApiConfiguration.
     */
    public static function isNotRetrySafeWriteCandidate(mixed $operation): bool
    {
        return !\is_string($operation) || Operation::tryFrom($operation)?->retryClass() !== OperationRetryClass::UnspecifiedWrite;
    }

    /**
     * The empty string is the dummy value of an environment variable placeholder while the container is validated:
     * an empty variable fails at runtime instead (Environment::from()).
     */
    public static function isUnknownEnvironment(mixed $environment): bool
    {
        return !\in_array($environment, ['', ...array_map(static fn(Environment $case): string => $case->value, Environment::cases())], true);
    }

    public static function isUnknownTimeZone(mixed $zone): bool
    {
        return !\is_string($zone) || !\in_array($zone, DateTimeZone::listIdentifiers(), true);
    }

    public static function isInvalidOptionalInterval(mixed $value): bool
    {
        return $value !== null && self::isInvalidInterval($value);
    }

    public static function isInvalidInterval(mixed $value): bool
    {
        try {
            new DateInterval(\is_string($value) ? $value : '');
        } catch (Throwable) {
            return true;
        }

        return false;
    }

    private static function seconds(DateInterval $interval): int
    {
        $origin = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

        return $origin->add($interval)->getTimestamp() - $origin->getTimestamp();
    }
}
