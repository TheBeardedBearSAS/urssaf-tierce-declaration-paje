<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer;

/**
 * Result of operation 020 `OutputVerificationPe` (api-reference §2.1): `employeurAutoriseOk` and `etatCompteEnLigne`.
 *
 * Every field of the response is optional in the Swagger; decoding rule for the response mapper (api-reference §5):
 * an absent `employeurAutoriseOk` is `false` and an absent `etatCompteEnLigne` is `OnlineAccountState::of('')`
 * (undocumented state): an absent field is never a reason to reject the response.
 */
final readonly class EmployerVerification
{
    private function __construct(
        private bool $authorised,
        private OnlineAccountState $state,
    ) {}

    public static function of(bool $authorised, OnlineAccountState $state): self
    {
        return new self($authorised, $state);
    }

    public function authorised(): bool
    {
        return $this->authorised;
    }

    public function state(): OnlineAccountState
    {
        return $this->state;
    }
}
