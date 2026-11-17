<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Instant;

/**
 * Instants of a {@see Mandate}: registration, cancellation and last update.
 */
final readonly class MandateTimeline
{
    private function __construct(
        private Instant $registeredAt,
        private ?Instant $cancelledAt,
        private Instant $updatedAt,
    ) {}

    public static function registered(Instant $at): self
    {
        return new self($at, null, $at);
    }

    public function registeredAt(): Instant
    {
        return $this->registeredAt;
    }

    public function cancelledAt(): ?Instant
    {
        return $this->cancelledAt;
    }

    public function updatedAt(): Instant
    {
        return $this->updatedAt;
    }

    public function withCancellation(Instant $at): self
    {
        return new self($this->registeredAt, $at, $at);
    }

    public function equals(self $other): bool
    {
        return $this->registeredAt->equals($other->registeredAt)
            && $this->cancelledAt?->toIso8601() === $other->cancelledAt?->toIso8601()
            && $this->updatedAt->equals($other->updatedAt);
    }
}
