<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Http;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Http\ApiRequest;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\JsonObject;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Fake\RejectingRateLimiter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\Stacks;

final class JsonApiClientRateLimitTest extends TestCase
{
    public function testItAcquiresTheRateLimiterOncePerCallForItsOperation(): void
    {
        $stack = Stacks::sandbox();
        $stack->respondToken();
        $stack->respond(200, '{}');
        $stack->respond(200, '{}');
        $client = $stack->jsonClient();

        $client->send(Stacks::tenant(), ApiRequest::get(Operation::VerifyEmployee, '/p', []));
        $client->send(Stacks::tenant(), ApiRequest::post(Operation::DeclareChildminder, '/q', [], JsonObject::of([])));

        static::assertSame(['tenant-1:021', 'tenant-1:050'], $stack->rateLimiter->acquired());
    }

    public function testTheTokenCallIsNotCounted(): void
    {
        $stack = Stacks::sandbox();
        $stack->respondToken();
        $stack->respond(200, '{}');

        $stack->jsonClient()->send(
            Stacks::tenant(),
            ApiRequest::get(Operation::VerifyEmployee, '/p', []),
        );

        static::assertCount(2, $stack->requests());
        static::assertSame(['tenant-1:021'], $stack->rateLimiter->acquired());
    }

    public function testARefusedAcquisitionSendsNothingAndFetchesNoToken(): void
    {
        $stack = Stacks::sandbox();
        $client = $stack->jsonClientWith(new RejectingRateLimiter());

        try {
            $client->send(
                Stacks::tenant(),
                ApiRequest::post(Operation::DeclareHomeCare, '/p', [], JsonObject::of([])),
            );
            static::fail('Expected the rate limit to be enforced.');
        } catch (RateLimitExceeded $exception) {
            static::assertSame(30, $exception->retryAfterSeconds());
            static::assertSame(Operation::DeclareHomeCare, $exception->operation());
        }
        static::assertSame([], $stack->requests());
        static::assertSame([], $stack->tokens->keys());
        static::assertSame([], $stack->logger->records());
    }
}
