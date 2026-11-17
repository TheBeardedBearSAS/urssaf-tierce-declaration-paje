<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Message;

/**
 * Asks to refresh (method 020) one tracked employer, e.g. dispatched with a DelayStamp after the mandate notification (030).
 * Holds identifiers only (no personal data, ADR 0007); safe to retry, it is a read (ADR 0013, decision 2).
 */
final readonly class SyncEmployerStatusMessage
{
    public function __construct(public string $tenantId, public string $employerAccountId) {}
}
