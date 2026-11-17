<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Instant;

/**
 * Mutable part of an {@see EmployerAccount}: stage, last method 020 snapshot with the instant it was read, and last
 * update.
 */
final readonly class EmployerTracking
{
    public function __construct(
        private EmployerStage $stage,
        private ?EmployerVerification $lastVerification,
        private ?Instant $lastCheckedAt,
        private Instant $updatedAt,
    ) {}

    public static function tracked(Instant $at): self
    {
        return new self(EmployerStage::Tracked, null, null, $at);
    }

    public function stage(): EmployerStage
    {
        return $this->stage;
    }

    public function lastVerification(): ?EmployerVerification
    {
        return $this->lastVerification;
    }

    public function lastCheckedAt(): ?Instant
    {
        return $this->lastCheckedAt;
    }

    public function updatedAt(): Instant
    {
        return $this->updatedAt;
    }

    public function withStage(EmployerStage $stage, Instant $now): self
    {
        return new self($stage, $this->lastVerification, $this->lastCheckedAt, $now);
    }

    public function withVerification(EmployerVerification $verification, Instant $now): self
    {
        return new self($this->stage, $verification, $now, $now);
    }

    /**
     * Whether the given snapshot is the last one.
     */
    public function hasVerification(EmployerVerification $verification): bool
    {
        return $this->lastVerification instanceof EmployerVerification && self::sameVerification($this->lastVerification, $verification);
    }

    public function equals(self $other): bool
    {
        return $this->stage === $other->stage
            && $this->hasSameVerificationAs($other)
            && $this->lastCheckedAt?->toIso8601() === $other->lastCheckedAt?->toIso8601()
            && $this->updatedAt->equals($other->updatedAt);
    }

    private function hasSameVerificationAs(self $other): bool
    {
        if (!$this->lastVerification instanceof EmployerVerification || !$other->lastVerification instanceof EmployerVerification) {
            return $this->lastVerification === $other->lastVerification;
        }

        return self::sameVerification($this->lastVerification, $other->lastVerification);
    }

    private static function sameVerification(EmployerVerification $left, EmployerVerification $right): bool
    {
        return $left->authorised() === $right->authorised() && $left->state()->equals($right->state());
    }
}
