<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Http;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\AccessToken;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Credentials;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Fake\StaticCredentialsProvider;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\Stack;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\Stacks;

final class OAuth2TokenResponseTest extends TestCase
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
    public static function unexpectedStatuses(): iterable
    {
        yield 'redirect' => [302];
        yield 'multiple choices' => [300];
        yield 'not found' => [404];
        yield 'conflict' => [409];
        yield 'teapot' => [418];
        yield 'informational' => [199];
    }

    #[DataProvider('unexpectedStatuses')]
    public function testOtherStatusesAreUnexpected(int $status): void
    {
        $stack = Stacks::sandbox();
        $stack->respond($status, '{"access_token":"tok","expires_in":3600}');

        $this->expectException(UnexpectedUrssafResponse::class);
        $this->expectExceptionMessage(\sprintf('HTTP %d', $status));

        $this->fetch($stack);
    }

    public function testAnyTwoHundredStatusIsASuccess(): void
    {
        $stack = Stacks::sandbox();
        $stack->respond(299, '{"access_token":"edge-token","expires_in":10}');

        static::assertSame('edge-token', $this->fetch($stack));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidBodies(): iterable
    {
        yield 'not json' => ['<html>'];
        yield 'not an object' => ['"token"'];
        yield 'empty body' => [''];
        yield 'empty list' => ['[]'];
        yield 'list of values' => ['["t",3600]'];
        yield 'missing token' => ['{"expires_in":3600}'];
        yield 'empty token' => ['{"access_token":"","expires_in":3600}'];
        yield 'missing lifetime' => ['{"access_token":"t"}'];
        yield 'sub-second lifetime' => ['{"access_token":"t","expires_in":0.5}'];
        yield 'zero lifetime' => ['{"access_token":"t","expires_in":0}'];
        yield 'negative lifetime' => ['{"access_token":"t","expires_in":-5}'];
        yield 'textual lifetime' => ['{"access_token":"t","expires_in":"soon"}'];
        yield 'numeric string lifetime' => ['{"access_token":"t","expires_in":"3600"}'];
    }

    #[DataProvider('invalidBodies')]
    public function testUnusableTokenResponsesAreRejected(string $body): void
    {
        $stack = Stacks::sandbox();
        $stack->respond(200, $body);

        $this->expectException(UnexpectedUrssafResponse::class);

        $this->fetch($stack);
    }

    public function testAOneSecondLifetimeIsAccepted(): void
    {
        $stack = Stacks::sandbox();
        $stack->respond(200, '{"access_token":"short","expires_in":1}');

        static::assertSame('short', $this->fetch($stack));
    }

    public function testAFractionalLifetimeIsTruncatedToWholeSeconds(): void
    {
        $stack = Stacks::sandbox();
        $stack->respond(200, '{"access_token":"fractional","expires_in":3600.9}');

        static::assertSame('fractional', $this->fetch($stack));
        static::assertSame(1_704_103_200 + 3600, $this->storedExpiry($stack));
    }

    public function testTheExpiryIsTheIssueInstantPlusTheLifetime(): void
    {
        $stack = Stacks::sandbox();
        $stack->respond(201, '{"access_token":"created","expires_in":120}');

        static::assertSame('created', $this->fetch($stack));
        static::assertSame(1_704_103_200 + 120, $this->storedExpiry($stack));
    }

    private function storedExpiry(Stack $stack): int
    {
        $key = $stack->tokens->keys()[0];
        $token = $stack->tokens->get($key);
        static::assertInstanceOf(AccessToken::class, $token);

        return $token->expiresAt()->toDateTimeImmutable()->getTimestamp();
    }
}
