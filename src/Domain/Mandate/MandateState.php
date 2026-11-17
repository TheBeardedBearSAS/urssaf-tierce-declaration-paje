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

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvalidStateTransition;

/**
 * State of a {@see Mandate} (ADR 0006): registered (method 030 succeeded, or imported) or cancelled (method 031
 * succeeded, final). Method 020 says nothing about the mandate in PAJE, so there is no confirmation state.
 */
enum MandateState: string
{
    case Registered = 'registered';
    case Cancelled = 'cancelled';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Registered => [self::Cancelled],
            self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return \in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * @throws InvalidStateTransition "mandate.invalid_state_transition"
     */
    public function transitionTo(self $target): self
    {
        if (!$this->canTransitionTo($target)) {
            throw InvalidStateTransition::between('mandate', $this->value, $target->value);
        }

        return $target;
    }

    /**
     * Not cancelled.
     */
    public function isActive(): bool
    {
        return $this !== self::Cancelled;
    }

    public function isFinal(): bool
    {
        return $this->allowedTransitions() === [];
    }
}
