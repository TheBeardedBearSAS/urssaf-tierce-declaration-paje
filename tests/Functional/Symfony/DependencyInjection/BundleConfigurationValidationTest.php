<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\DependencyInjection;

use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\App\TestKernel;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Support\Harness;

/**
 * Every rule of the configuration tree and every built-in constraint is rejected at build time (ADR 0013).
 */
final class BundleConfigurationValidationTest extends TestCase
{
    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('invalidRules')]
    #[DataProvider('invalidSafetyRules')]
    #[DataProvider('invalidConstraints')]
    public function testRejectsAnInvalidConfiguration(array $config, string $message): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($message);

        Harness::boot($config);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidRules(): iterable
    {
        yield 'unknown environment' => [['environment' => 'staging'], 'Unknown environment "staging": use "sandbox" or "production".'];
        yield 'unknown time zone' => [['workflow' => ['business_timezone' => 'Mars/Phobos']], 'Unknown time zone "Mars\/Phobos".'];
        yield 'invalid retention' => [['outbox' => ['retention' => 'a month']], 'Invalid ISO 8601 duration "a month".'];
        yield 'invalid stale duration' => [['workflow' => ['stale_submission_after' => 'soon']], 'Invalid ISO 8601 duration "soon".'];
        yield 'invalid sync frequency' => [['scheduler' => ['frequencies' => ['sync_employer_statuses' => 'often']]], 'Invalid ISO 8601 duration "often".'];
        yield 'invalid reconcile frequency' => [['scheduler' => ['frequencies' => ['reconcile_stale_declarations' => 'often']]], 'Invalid ISO 8601 duration "often".'];
        yield 'invalid relay frequency' => [['scheduler' => ['frequencies' => ['relay_outbox' => 'often']]], 'Invalid ISO 8601 duration "often".'];
        yield 'invalid purge frequency' => [['scheduler' => ['frequencies' => ['purge_outbox' => 'often']]], 'Invalid ISO 8601 duration "often".'];
        yield 'credentials and provider' => [['credentials_provider' => 'my.provider'], '"credentials" and "credentials_provider" are mutually exclusive.'];
        yield 'no credentials source' => [['credentials' => null], 'Configure either "credentials" or "credentials_provider".'];
        yield 'simulated gateway in production' => [['environment' => 'production', 'gateway' => ['simulated' => true]], '"gateway.simulated" is meant for tests and cannot be used with "environment: production".'];
        yield 'outbox without storage' => [
            [
                'credentials' => null,
                'credentials_provider' => 'test.credentials',
                'outbox' => ['enabled' => true],
                'persistence' => ['driver' => 'custom', 'employer_account_repository' => 'a', 'mandate_repository' => 'm', 'declaration_record_repository' => 'd'],
            ],
            'The "custom" persistence driver requires "outbox.service" when the outbox is enabled.',
        ];
        yield 'custom driver without repositories' => [['persistence' => ['driver' => 'custom']], 'The "custom" driver requires "employer_account_repository", "mandate_repository" and "declaration_record_repository".'];
        yield 'custom driver with one repository' => [['persistence' => ['driver' => 'custom', 'employer_account_repository' => 'a']], 'The "custom" driver requires "employer_account_repository"'];
        yield 'default page above max' => [['workflow' => ['default_page_size' => 200, 'max_page_size' => 100]], '"default_page_size" must not be greater than "max_page_size".'];
        yield 'same header names' => [['request_id_header' => 'X-Id', 'application_id_header' => ['name' => 'x-id', 'value' => 'v']], '"request_id_header" and "application_id_header.name" must be different headers.'];
        yield 'header name only' => [['application_id_header' => ['name' => 'X-Application']], '"application_id_header" needs both "name" and "value", or neither.'];
        yield 'header value only' => [['application_id_header' => ['value' => 'my-application']], '"application_id_header" needs both "name" and "value", or neither.'];
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidSafetyRules(): iterable
    {
        yield 'stale submission shorter than the timeout' => [['http_timeout' => 120.0, 'workflow' => ['stale_submission_after' => 'PT1M']], '"workflow.stale_submission_after" must be longer than "http_timeout"'];
        yield 'stale submission equal to the timeout' => [['http_timeout' => 60.0, 'workflow' => ['stale_submission_after' => 'PT1M']], '"workflow.stale_submission_after" must be longer than "http_timeout"'];
        yield 'rate limiter without limits' => [['rate_limiter' => ['enabled' => true]], '"rate_limiter.enabled" requires "rate_limiter.limit" and "rate_limiter.window".'];
        yield 'rate limiter without window' => [['rate_limiter' => ['enabled' => true, 'limit' => 10]], '"rate_limiter.enabled" requires "rate_limiter.limit" and "rate_limiter.window".'];
        yield 'rate limiter without limit' => [['rate_limiter' => ['enabled' => true, 'window' => 60]], '"rate_limiter.enabled" requires "rate_limiter.limit" and "rate_limiter.window".'];
        yield 'safe retry of a read' => [['retry_safe_writes' => ['020']], 'Unknown operation "020": only "023", "030" and "031" can be declared safe to retry'];
        yield 'safe retry of a never resent write' => [['retry_safe_writes' => ['050']], 'Unknown operation "050"'];
        yield 'safe retry given as an integer (unquoted YAML)' => [['retry_safe_writes' => [23]], 'only "023", "030" and "031" can be declared safe to retry'];
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidConstraints(): iterable
    {
        yield 'negative safety margin' => [['token_safety_margin' => -1], 'token_safety_margin'];
        yield 'http timeout below 0.1' => [['http_timeout' => 0.05], 'http_timeout'];
        yield 'empty user agent' => [['user_agent' => ''], 'user_agent'];
        yield 'rate limit below 1' => [['rate_limiter' => ['limit' => 0]], 'rate_limiter.limit'];
        yield 'rate window below 1' => [['rate_limiter' => ['window' => 0]], 'rate_limiter.window'];
        yield 'lock mode' => [['lock' => ['enabled' => 'sometimes']], 'lock.enabled'];
        yield 'lock ttl below 1' => [['lock' => ['ttl' => 0.5]], 'lock.ttl'];
        yield 'empty lock factory' => [['lock' => ['factory' => '']], 'lock.factory'];
        yield 'empty outbox sink' => [['outbox' => ['sink' => '']], 'outbox.sink'];
        yield 'empty outbox bus' => [['outbox' => ['bus' => '']], 'outbox.bus'];
        yield 'empty schedule name' => [['scheduler' => ['name' => '']], 'scheduler.name'];
        yield 'empty messenger bus' => [['messenger' => ['bus' => '']], 'messenger.bus'];
        yield 'empty cache pool' => [['token_store' => ['cache_pool' => '']], 'token_store.cache_pool'];
        yield 'empty tenant' => [['tenants' => ['']], 'tenants'];
        yield 'no attempts' => [['workflow' => ['max_submission_attempts' => 0]], 'max_submission_attempts'];
        yield 'no default page size' => [['workflow' => ['default_page_size' => 0]], 'default_page_size'];
        yield 'no max page size' => [['workflow' => ['default_page_size' => 1, 'max_page_size' => 0]], 'workflow.max_page_size'];
        yield 'unknown driver' => [['persistence' => ['driver' => 'mongo']], 'persistence.driver'];
        // @mago-expect lint:no-literal-password Fake credentials of a test configuration.
        yield 'empty client id' => [['credentials' => ['tenant-1' => ['client_id' => '', 'client_secret' => 'x']]], 'client_id'];
        yield 'empty client secret' => [['credentials' => ['tenant-1' => ['client_id' => 'x', 'client_secret' => '']]], 'client_secret'];
        yield 'foster carer option of another package' => [['workflow' => ['allow_foster_carer_declarations' => true]], 'allow_foster_carer_declarations'];
        yield 'agreement encoding of another package' => [['agreement_encoding' => 'numeric'], 'agreement_encoding'];
    }

    public function testTheDoctrineDriverNeedsDoctrineBundle(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The "doctrine" persistence driver requires DoctrineBundle (doctrine/doctrine-bundle): register it, or set "persistence.driver" to "in_memory" or "custom".');

        Harness::boot(['persistence' => ['driver' => 'doctrine']]);
    }

    public function testTheEnvironmentIsRequired(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('environment');

        (new TestKernel(['environment' => null]))->boot();
    }
}
