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
 * How an {@see EmployerAccount} came to be tracked (ADR 0006).
 */
enum EmployerOrigin: string
{
    /** Tracked by a use case of this package (e.g. a first verification or mandate registration). */
    case Tracked = 'tracked';

    /** Existing Pajemploi employer adopted by the platform. */
    case Imported = 'imported';
}
