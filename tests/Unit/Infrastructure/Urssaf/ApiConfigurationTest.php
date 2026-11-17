<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf;

use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Environment;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\HttpHeader;

/**
 * One method per case.
 *
 * @mago-expect lint:too-many-methods
 */
final class ApiConfigurationTest extends TestCase
{
    /**
     * @return iterable<string, array{Environment, string, string, string}>
     */
    public static function provideEnvironments(): iterable
    {
        yield 'sandbox' => [
            Environment::Sandbox,
            'https://api-edi.urssaf.fr',
            'https://api-edi.urssaf.fr/api/oauth/v1/token',
            'https://api-edi.urssaf.fr/tiersdecl/v1/paje/employeurs',
        ];
        yield 'production' => [
            Environment::Production,
            'https://api.urssaf.fr',
            'https://api.urssaf.fr/api/oauth/v1/token',
            'https://api.urssaf.fr/tiersdecl/v1/paje/employeurs',
        ];
    }

    #[DataProvider('provideEnvironments')]
    public function testItResolvesTheEnvironmentUris(
        Environment $environment,
        string $baseUri,
        string $tokenUri,
        string $apiUri,
    ): void {
        $configuration = ApiConfiguration::forEnvironment($environment);

        static::assertSame($environment, $configuration->environment());
        static::assertSame($baseUri, $configuration->baseUri());
        static::assertSame($tokenUri, $configuration->tokenUri());
        static::assertSame($apiUri, $configuration->apiUri('/employeurs'));
    }

    public function testItHasTheDocumentedDefaults(): void
    {
        $configuration = ApiConfiguration::forEnvironment(Environment::Sandbox);

        static::assertSame(60, $configuration->tokenSafetyMarginSeconds());
        static::assertSame('thebeardedbear-urssaf-tierce-declaration-paje', $configuration->userAgent());
        static::assertSame('Europe/Paris', $configuration->businessTimeZone()->getName());
        static::assertSame([], $configuration->retrySafeWrites());
        static::assertNull($configuration->requestIdHeaderName());
        static::assertNull($configuration->applicationIdHeader());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideBaseUriOverrides(): iterable
    {
        yield 'host only' => ['https://proxy.example.test', 'https://proxy.example.test'];
        yield 'trailing slash' => ['https://proxy.example.test/', 'https://proxy.example.test'];
        yield 'path with trailing slashes' => ['https://h/base//', 'https://h/base'];
        yield 'path' => ['https://h/base', 'https://h/base'];
        yield 'plain http with a port' => ['http://localhost:8080', 'http://localhost:8080'];
        yield 'upper case scheme' => ['HTTPS://h', 'HTTPS://h'];
    }

    #[DataProvider('provideBaseUriOverrides')]
    public function testItAppliesTheBaseUriOverride(string $override, string $expected): void
    {
        $configuration = new ApiConfiguration(Environment::Production, $override);

        static::assertSame($expected, $configuration->baseUri());
        static::assertSame($expected . '/api/oauth/v1/token', $configuration->tokenUri());
        static::assertSame($expected . '/tiersdecl/v1/paje/mandats', $configuration->apiUri('/mandats'));
    }

    public function testItExposesCustomSettings(): void
    {
        $timeZone = new DateTimeZone('UTC');
        $application = HttpHeader::of('X-Application-Id', 'app-42');

        $configuration = new ApiConfiguration(
            Environment::Production,
            null,
            'X-Request-Id',
            $application,
            5,
            'my-agent/1.0 (contact)',
            $timeZone,
            [Operation::RegisterMandate, Operation::CancelMandate],
        );

        static::assertSame('X-Request-Id', $configuration->requestIdHeaderName());
        static::assertSame($application, $configuration->applicationIdHeader());
        static::assertSame(5, $configuration->tokenSafetyMarginSeconds());
        static::assertSame('my-agent/1.0 (contact)', $configuration->userAgent());
        static::assertSame($timeZone, $configuration->businessTimeZone());
        static::assertSame([Operation::RegisterMandate, Operation::CancelMandate], $configuration->retrySafeWrites());
    }

    public function testItAcceptsAZeroSafetyMargin(): void
    {
        static::assertSame(
            0,
            (new ApiConfiguration(Environment::Sandbox, null, null, null, 0))->tokenSafetyMarginSeconds(),
        );
    }

    public function testItAcceptsTwoDifferentHeaderNamesInEitherRole(): void
    {
        $configuration = new ApiConfiguration(Environment::Sandbox, null, 'X-A', HttpHeader::of('X-B', 'v'));

        static::assertSame('X-A', $configuration->requestIdHeaderName());
        static::assertSame('X-B', $configuration->applicationIdHeader()?->name());
    }

    public function testItAcceptsAnApplicationIdHeaderWithoutRequestIdHeader(): void
    {
        $configuration = new ApiConfiguration(Environment::Sandbox, null, null, HttpHeader::of('X-B', 'v'));

        static::assertNull($configuration->requestIdHeaderName());
        static::assertSame('X-B', $configuration->applicationIdHeader()?->name());
    }

    /**
     * @return iterable<string, array{Operation, bool}>
     */
    public static function provideDefaultRetrySafety(): iterable
    {
        foreach (Operation::cases() as $operation) {
            yield $operation->value => [
                $operation,
                !\in_array($operation, [
                    Operation::DeclareChildminder,
                    Operation::DeclareHomeCare,
                    Operation::AssociateEmployee,
                    Operation::RegisterMandate,
                    Operation::CancelMandate,
                ], true),
            ];
        }
    }

    #[DataProvider('provideDefaultRetrySafety')]
    public function testOnlyReadsAndPreDeclarationsAreSafeToRetryByDefault(Operation $operation, bool $safe): void
    {
        static::assertSame($safe, ApiConfiguration::forEnvironment(Environment::Sandbox)->isSafeToRetry($operation));
    }

    public function testDeclaredWritesAreSafeToRetryAndOnlyThose(): void
    {
        $configuration = new ApiConfiguration(
            Environment::Sandbox,
            null,
            null,
            null,
            60,
            ApiConfiguration::DEFAULT_USER_AGENT,
            new DateTimeZone('Europe/Paris'),
            [Operation::AssociateEmployee, Operation::CancelMandate],
        );

        static::assertTrue($configuration->isSafeToRetry(Operation::AssociateEmployee));
        static::assertTrue($configuration->isSafeToRetry(Operation::CancelMandate));
        static::assertFalse($configuration->isSafeToRetry(Operation::RegisterMandate));
        static::assertFalse($configuration->isSafeToRetry(Operation::DeclareChildminder));
        static::assertTrue($configuration->isSafeToRetry(Operation::VerifyEmployer));
    }
}
