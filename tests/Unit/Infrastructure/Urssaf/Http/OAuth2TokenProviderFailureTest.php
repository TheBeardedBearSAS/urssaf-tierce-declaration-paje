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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Credentials;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Http\TransportError;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Fake\StaticCredentialsProvider;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Fake\TransportFailure;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\Stack;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\Stacks;

final class OAuth2TokenProviderFailureTest extends TestCase
{
    private function fetch(Stack $stack): string
    {
        return $stack->tokenProvider()->accessToken(
            Credentials::of(StaticCredentialsProvider::CLIENT_ID, StaticCredentialsProvider::SHARED_VALUE),
            Operation::DeclareHomeCare,
        );
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function authenticationStatuses(): iterable
    {
        yield 'bad request' => [400];
        yield 'unauthorized' => [401];
        yield 'forbidden' => [403];
    }


    #[DataProvider('authenticationStatuses')]
    public function testBadCredentialsFailAuthentication(int $status): void
    {
        $stack = Stacks::sandbox();
        $stack->respond($status, '{"error":"invalid_client"}');

        try {
            $this->fetch($stack);
            static::fail('Expected an authentication failure.');
        } catch (UrssafAuthenticationFailed $exception) {
            static::assertSame($status, $exception->httpStatus());
        }
        static::assertSame([], $stack->tokens->keys());
    }

    /**
     * @return iterable<string, array{int, array<string, string>, int|null}>
     */
    public static function unavailableStatuses(): iterable
    {
        yield 'quota with Retry-After' => [429, ['Retry-After' => '17'], 17];
        yield 'quota without Retry-After' => [429, [], null];
        yield 'server error' => [500, [], null];
        yield 'bad gateway' => [502, [], null];
        yield 'service unavailable' => [503, ['Retry-After' => '5'], 5];
        yield 'security gateway' => [512, [], null];
        yield 'last server status' => [599, [], null];
    }

    /**
     * @param array<string, string> $headers
     */
    #[DataProvider('unavailableStatuses')]
    public function testQuotaAndServerErrorsAreRetryable(int $status, array $headers, ?int $retryAfter): void
    {
        $stack = Stacks::sandbox();
        $stack->respond($status, '', $headers);

        try {
            $this->fetch($stack);
            static::fail('Expected an unavailable exception.');
        } catch (UrssafUnavailable $exception) {
            static::assertSame($status, $exception->httpStatus());
            static::assertSame($retryAfter, $exception->retryAfterSeconds());
            static::assertSame(Operation::DeclareHomeCare, $exception->operation());
            static::assertFalse($exception->mayHaveReachedUrssaf());
        }
    }

    public function testTransportErrorsAreRetryableAndKeepTheCause(): void
    {
        $stack = Stacks::sandbox();
        $stack->fail(new TransportFailure('connection refused https://h/x?nir=FICTIVE'));

        try {
            $this->fetch($stack);
            static::fail('Expected an unavailable exception.');
        } catch (UrssafUnavailable $exception) {
            static::assertNull($exception->httpStatus());
            static::assertInstanceOf(TransportError::class, $exception->getPrevious());
            static::assertStringNotContainsString('FICTIVE', $exception->getPrevious()->getMessage());
            static::assertSame(Operation::DeclareHomeCare, $exception->operation());
            static::assertFalse($exception->mayHaveReachedUrssaf());
        }
    }
}
