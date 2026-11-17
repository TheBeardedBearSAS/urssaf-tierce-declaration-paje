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
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Fake\StaticCredentialsProvider;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Fake\TransportFailure;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\Stacks;

final class OAuth2TokenProviderLoggingTest extends TestCase
{
    private function credentials(): Credentials
    {
        return Credentials::of(StaticCredentialsProvider::CLIENT_ID, StaticCredentialsProvider::SHARED_VALUE);
    }

    public function testItLogsTheTokenCallWithoutAnySecret(): void
    {
        $stack = Stacks::stepping(250_000);
        $stack->respondToken();

        $stack->tokenProvider()->accessToken($this->credentials(), Operation::VerifyEmployer);

        $records = $stack->logger->records();
        static::assertCount(1, $records);
        static::assertSame('info', $records[0]['level']);
        static::assertSame(
            ['method' => 'POST', 'path' => '/api/oauth/v1/token', 'status' => 200, 'duration_ms' => 250],
            $records[0]['context'],
        );
        static::assertStringNotContainsString(StaticCredentialsProvider::SHARED_VALUE, $stack->logger->dump());
        static::assertStringNotContainsString('fictitious-access-token-0001', $stack->logger->dump());
    }

    public function testItLogsTransportFailuresWithoutTheMessage(): void
    {
        $stack = Stacks::sandbox();
        $stack->fail(new TransportFailure('cannot connect to host with client_secret=' . StaticCredentialsProvider::SHARED_VALUE));

        try {
            $stack->tokenProvider()->accessToken($this->credentials(), Operation::VerifyEmployer);
            static::fail('Expected an unavailable exception.');
        } catch (UrssafUnavailable) {
            $records = $stack->logger->records();
            static::assertCount(1, $records);
            static::assertSame('warning', $records[0]['level']);
            static::assertSame(['exception' => TransportFailure::class], $records[0]['context']);
            static::assertStringNotContainsString(StaticCredentialsProvider::SHARED_VALUE, $stack->logger->dump());
        }
    }
}
