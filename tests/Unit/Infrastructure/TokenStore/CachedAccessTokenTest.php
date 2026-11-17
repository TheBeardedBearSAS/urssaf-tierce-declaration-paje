<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\TokenStore;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\AccessToken;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Instant;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\TokenStore\CachedAccessToken;

final class CachedAccessTokenTest extends TestCase
{
    public function testSerializesValueAndExpiryTimestamp(): void
    {
        $token = AccessToken::of('tok3n', Instant::fromDateTime(new DateTimeImmutable('2100-01-01T00:00:00+00:00')));

        static::assertSame(['value' => 'tok3n', 'expiresAt' => 4_102_444_800], CachedAccessToken::toArray($token));
    }

    public function testRestoresAValidToken(): void
    {
        $token = CachedAccessToken::fromArray(['value' => 'tok3n', 'expiresAt' => 4_102_444_800]);

        static::assertInstanceOf(AccessToken::class, $token);
        static::assertSame('tok3n', $token->value());
        static::assertSame(4_102_444_800, $token->expiresAt()->toDateTimeImmutable()->getTimestamp());
        static::assertSame('2100-01-01T00:00:00Z', $token->expiresAt()->toIso8601());
    }

    public function testRoundTripKeepsTheToken(): void
    {
        $original = AccessToken::of('tok3n', Instant::fromDateTime(new DateTimeImmutable('2100-01-01T00:00:00+00:00')));

        $restored = CachedAccessToken::fromArray(CachedAccessToken::toArray($original));

        static::assertNotNull($restored);
        static::assertSame($original->value(), $restored->value());
        static::assertSame($original->expiresAt()->toDateTimeImmutable()->getTimestamp(), $restored->expiresAt()->toDateTimeImmutable()->getTimestamp());
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function corruptedData(): iterable
    {
        yield 'null' => [null];
        yield 'string' => ['garbage'];
        yield 'missing value' => [['expiresAt' => 4_102_444_800]];
        yield 'missing expiry' => [['value' => 'tok3n']];
        yield 'null value' => [['value' => null, 'expiresAt' => 4_102_444_800]];
        yield 'integer value' => [['value' => 123, 'expiresAt' => 4_102_444_800]];
        yield 'string expiry' => [['value' => 'tok3n', 'expiresAt' => '4_102_444_800']];
        yield 'float expiry' => [['value' => 'tok3n', 'expiresAt' => 4_102_444_800.0]];
        yield 'blank value' => [['value' => '   ', 'expiresAt' => 4_102_444_800]];
    }

    #[DataProvider('corruptedData')]
    public function testCorruptedDataIsNotRestored(mixed $data): void
    {
        static::assertNull(CachedAccessToken::fromArray($data));
    }
}
