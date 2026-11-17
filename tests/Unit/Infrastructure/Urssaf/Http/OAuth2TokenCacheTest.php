<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Http;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Credentials;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Environment;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Fake\StaticCredentialsProvider;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\Stacks;

final class OAuth2TokenCacheTest extends TestCase
{
    private function credentials(string $clientId = StaticCredentialsProvider::CLIENT_ID): Credentials
    {
        return Credentials::of($clientId, StaticCredentialsProvider::SHARED_VALUE);
    }

    private const ISSUED = 'fictitious-access-token-0001';

    public function testItCachesTheTokenUnderAPsr6SafeKeyWithoutSecrets(): void
    {
        $stack = Stacks::sandbox();
        $stack->respondToken();
        $provider = $stack->tokenProvider();

        $provider->accessToken($this->credentials(), Operation::VerifyEmployer);
        $second = $provider->accessToken($this->credentials(), Operation::VerifyEmployee);

        static::assertSame(self::ISSUED, $second);
        static::assertCount(1, $stack->requests());
        $expectedKey = 'urssaf_paje.' . hash('sha256', 'sandbox|https://api-edi.urssaf.fr|' . StaticCredentialsProvider::CLIENT_ID);
        static::assertSame([$expectedKey], $stack->tokens->keys());
        static::assertSame($expectedKey, $provider->cacheKey($this->credentials()));
        static::assertMatchesRegularExpression('/^urssaf_paje\.[0-9a-f]{64}$/', $expectedKey);
        static::assertStringNotContainsString(StaticCredentialsProvider::SHARED_VALUE, $expectedKey);
    }

    public function testTheCacheKeyDependsOnClientEnvironmentAndBaseUri(): void
    {
        $keys = [];
        $variants = [
            [ApiConfiguration::forEnvironment(Environment::Sandbox), 'client-a'],
            [ApiConfiguration::forEnvironment(Environment::Sandbox), 'client-b'],
            [ApiConfiguration::forEnvironment(Environment::Production), 'client-a'],
            [new ApiConfiguration(Environment::Sandbox, 'https://proxy.example.test'), 'client-a'],
            [new ApiConfiguration(Environment::Sandbox, 'https://other-proxy.example.test'), 'client-a'],
        ];
        foreach ($variants as [$configuration, $clientId]) {
            $stack = Stacks::configured($configuration);
            $stack->respondToken();
            $stack->tokenProvider()->accessToken($this->credentials($clientId), Operation::VerifyEmployer);
            $keys[] = $stack->tokens->keys()[0];
        }

        static::assertCount(5, array_unique($keys));
    }

    public function testInvalidateForcesANewToken(): void
    {
        $stack = Stacks::sandbox();
        $stack->respondToken();
        $stack->respond(200, '{"access_token":"second-token","expires_in":3600}');
        $provider = $stack->tokenProvider();
        $provider->accessToken($this->credentials(), Operation::VerifyEmployer);

        $provider->invalidate($this->credentials());

        static::assertSame([], $stack->tokens->keys());
        static::assertSame('second-token', $provider->accessToken($this->credentials(), Operation::VerifyEmployer));
    }

    public function testInvalidateOnlyAffectsTheGivenClient(): void
    {
        $stack = Stacks::sandbox();
        $stack->respondToken();
        $stack->respondToken();
        $provider = $stack->tokenProvider();
        $provider->accessToken($this->credentials('client-a'), Operation::VerifyEmployer);
        $provider->accessToken($this->credentials('client-b'), Operation::VerifyEmployer);

        $provider->invalidate($this->credentials('client-a'));

        static::assertSame([$provider->cacheKey($this->credentials('client-b'))], $stack->tokens->keys());
    }

    public function testACachedTokenSendsNoRequest(): void
    {
        $stack = Stacks::sandbox();
        $stack->respondToken();
        $provider = $stack->tokenProvider();
        $provider->accessToken($this->credentials(), Operation::VerifyEmployer);
        $stack->logger->clear();

        $provider->accessToken($this->credentials(), Operation::VerifyEmployer);

        static::assertCount(1, $stack->requests());
        static::assertSame([], $stack->logger->records());
    }
}
