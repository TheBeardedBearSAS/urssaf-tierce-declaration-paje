<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration;

/**
 * Kind of activity of a declaration: the choice of operation (`/ama/…` or `/ged/…`), not a wire value.
 */
enum DeclarationActivity: string
{
    case Childminder = 'childminder';
    case HomeCare = 'home_care';
}
