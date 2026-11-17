<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Service;

use TheBeardedBear\UrssafTierceDeclarationPaje\Contract\Event\IntegrationEvent;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\DomainEvent;

/**
 * Turns the domain events that matter to the host application into public integration events; the others (recorded
 * or requeued declarations, technical failures...) have none (ADR 0012).
 */
final class IntegrationEventTranslator
{
    /**
     * @return list<IntegrationEvent>
     */
    public static function translate(DomainEvent ...$events): array
    {
        $translated = [];
        foreach ($events as $event) {
            array_push(
                $translated,
                ...EmployerEventTranslator::translate($event),
                ...MandateEventTranslator::translate($event),
                ...DeclarationEventTranslator::translate($event),
            );
        }

        return $translated;
    }
}
