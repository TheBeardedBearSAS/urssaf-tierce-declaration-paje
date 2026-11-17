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
use TheBeardedBear\UrssafTierceDeclarationPaje\Contract\Event\MandateCancelled;
use TheBeardedBear\UrssafTierceDeclarationPaje\Contract\Event\MandateRegistered;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\Event\MandateCancelled as DomainMandateCancelled;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\Event\MandateRegistered as DomainMandateRegistered;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\DomainEvent;

/**
 * Integration events of the mandate workflow (ADR 0012). A mandate imported without any URSSAF call is published like
 * a registered one, its origin tells them apart.
 */
final class MandateEventTranslator
{
    /**
     * @return list<IntegrationEvent> the integration event of the domain event, none when it has none
     */
    public static function translate(DomainEvent $event): array
    {
        return match (true) {
            $event instanceof DomainMandateRegistered => [self::registered($event)],
            $event instanceof DomainMandateCancelled => [self::cancelled($event)],
            default => [],
        };
    }

    private static function registered(DomainMandateRegistered $event): MandateRegistered
    {
        $aggregateId = $event->mandateId()->value();
        $origin = $event->origin()->value;

        return new MandateRegistered(
            IntegrationEventFacts::id(MandateRegistered::TYPE, $aggregateId, $event->occurredAt(), [$origin]),
            IntegrationEventFacts::instant($event->occurredAt()),
            $event->tenantId()->value(),
            $aggregateId,
            $event->externalReference()->value(),
            $event->employerAccountId()->value(),
            $origin,
        );
    }

    private static function cancelled(DomainMandateCancelled $event): MandateCancelled
    {
        $aggregateId = $event->mandateId()->value();

        return new MandateCancelled(
            IntegrationEventFacts::id(MandateCancelled::TYPE, $aggregateId, $event->occurredAt(), []),
            IntegrationEventFacts::instant($event->occurredAt()),
            $event->tenantId()->value(),
            $aggregateId,
            $event->externalReference()->value(),
            $event->employerAccountId()->value(),
        );
    }
}
