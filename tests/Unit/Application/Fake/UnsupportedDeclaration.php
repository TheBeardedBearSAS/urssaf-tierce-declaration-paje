<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Fake;

use Override;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\Declaration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationActivity;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationCommon;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\DeclaredEmployee;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Declaration\RecordSamples;

/**
 * A declaration that is neither a childminder nor a home care declaration (the sum type of ADR 0004 is open at the type
 * level): delegates to a valid childminder declaration.
 */
final readonly class UnsupportedDeclaration implements Declaration
{
    #[Override]
    public function common(): DeclarationCommon
    {
        return RecordSamples::childminder()->common();
    }

    #[Override]
    public function employee(): DeclaredEmployee
    {
        return RecordSamples::childminder()->employee();
    }

    #[Override]
    public function children(): array
    {
        return RecordSamples::childminder()->children();
    }

    #[Override]
    public function activityType(): DeclarationActivity
    {
        return DeclarationActivity::Childminder;
    }
}
