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
 * Local follow-up stage of an {@see EmployerAccount} (ADR 0006): tracked, then mandate registered (method 030
 * succeeded), then mandate cancelled (method 031 succeeded). Stages never block a call by themselves (ADR 0009 does), so
 * there is no invalid transition. Method 020 says nothing about the mandate and never changes the stage.
 */
enum EmployerStage: string
{
    case Tracked = 'tracked';
    case MandateRegistered = 'mandate_registered';
    case MandateCancelled = 'mandate_cancelled';

    /**
     * Method 030 succeeded (or a mandate was imported): tracked and cancelled employers move to "mandate_registered".
     */
    public function afterMandateRegistered(): self
    {
        return self::MandateRegistered;
    }

    /**
     * Method 031 succeeded.
     */
    public function afterMandateCancelled(): self
    {
        return self::MandateCancelled;
    }
}
