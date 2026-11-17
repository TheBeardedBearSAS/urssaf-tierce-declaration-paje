<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Query;

use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\DeclarationView;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service\DeclarationLocator;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\RecordNotFound;

final readonly class GetDeclarationHandler
{
    public function __construct(private DeclarationLocator $locator) {}

    /**
     * @throws RecordNotFound "declaration_record.not_found"
     */
    public function __invoke(GetDeclaration $query): DeclarationView
    {
        return DeclarationView::fromRecord($this->locator->locate($query->tenantId, $query->declaration));
    }
}
