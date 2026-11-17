<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Clock;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\IdGenerator;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\RateLimiter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Id\RandomUuidGenerator;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Id\SecureRandomBytes;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\RateLimiter\NoopRateLimiter;

/**
 * Optional collaborators of the gateway, immutable; each one not given keeps its default:
 * - rate limiter: none ({@see NoopRateLimiter}): the URSSAF quota (HTTP 429) applies alone (ADR 0011); give a
 *   `SlidingWindowRateLimiter` (shared across workers if need be) to throttle locally;
 * - id generator: random UUIDs (used only when a request-id header is configured);
 * - clock: the system clock (UTC);
 * - logger: no logging.
 *
 *     GatewayOptions::defaults()->withLogger($logger)->withRateLimiter($sharedRateLimiter)
 */
final readonly class GatewayOptions
{
    private function __construct(
        private RateLimiter $rateLimiter,
        private IdGenerator $idGenerator,
        private Clock $clock,
        private LoggerInterface $logger,
    ) {}

    public static function defaults(): self
    {
        return new self(
            new NoopRateLimiter(),
            new RandomUuidGenerator(new SecureRandomBytes()),
            new UtcSystemClock(),
            new NullLogger(),
        );
    }

    public function withRateLimiter(RateLimiter $rateLimiter): self
    {
        return new self($rateLimiter, $this->idGenerator, $this->clock, $this->logger);
    }

    public function withIdGenerator(IdGenerator $idGenerator): self
    {
        return new self($this->rateLimiter, $idGenerator, $this->clock, $this->logger);
    }

    public function withClock(Clock $clock): self
    {
        return new self($this->rateLimiter, $this->idGenerator, $clock, $this->logger);
    }

    public function withLogger(LoggerInterface $logger): self
    {
        return new self($this->rateLimiter, $this->idGenerator, $this->clock, $logger);
    }

    public function rateLimiter(): RateLimiter
    {
        return $this->rateLimiter;
    }

    public function idGenerator(): IdGenerator
    {
        return $this->idGenerator;
    }

    public function clock(): Clock
    {
        return $this->clock;
    }

    public function logger(): LoggerInterface
    {
        return $this->logger;
    }
}
