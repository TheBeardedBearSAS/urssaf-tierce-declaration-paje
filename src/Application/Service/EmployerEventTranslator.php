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

use TheBeardedBear\UrssafTierceDeclarationPaje\Contract\Event\EmployerForgotten;
use TheBeardedBear\UrssafTierceDeclarationPaje\Contract\Event\EmployerStatusChanged;
use TheBeardedBear\UrssafTierceDeclarationPaje\Contract\Event\IntegrationEvent;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\Event\EmployerForgotten as DomainEmployerForgotten;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\Event\EmployerStageChanged;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\Event\EmployerStatusRefreshed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\DomainEvent;

/**
 * Integration events of the employer workflow (ADR 0012). Registering an employer has none: the catalogue starts with
 * its status and its mandates.
 */
final class EmployerEventTranslator
{
    /**
     * @return list<IntegrationEvent> the integration event of the domain event, none when it has none
     */
    public static function translate(DomainEvent $event): array
    {
        return match (true) {
            $event instanceof EmployerStatusRefreshed => [self::statusRefreshed($event)],
            $event instanceof EmployerStageChanged => [self::stageChanged($event)],
            $event instanceof DomainEmployerForgotten => [self::forgotten($event)],
            default => [],
        };
    }

    private static function forgotten(DomainEmployerForgotten $event): EmployerForgotten
    {
        $aggregateId = $event->accountId()->value();
        $mandateIds = array_map(static fn(MandateId $id): string => $id->value(), $event->mandateIds());
        $recordIds = array_map(static fn(DeclarationRecordId $id): string => $id->value(), $event->declarationRecordIds());

        return new EmployerForgotten(
            IntegrationEventFacts::id(EmployerForgotten::TYPE, $aggregateId, $event->occurredAt(), [...$mandateIds, '|', ...$recordIds]),
            IntegrationEventFacts::instant($event->occurredAt()),
            $event->tenantId()->value(),
            $aggregateId,
            $event->externalReference()->value(),
            $mandateIds,
            $recordIds,
        );
    }

    private static function statusRefreshed(EmployerStatusRefreshed $event): EmployerStatusChanged
    {
        $aggregateId = $event->accountId()->value();
        // Method 020 never changes the stage (ADR 0006): the stage before and after is the current one.
        $stage = $event->stage();
        $authorised = $event->verification()->authorised();
        $online = $event->verification()->state()->raw();

        return new EmployerStatusChanged(
            IntegrationEventFacts::id(
                EmployerStatusChanged::TYPE,
                $aggregateId,
                $event->occurredAt(),
                [$stage->value, $stage->value, $authorised ? '1' : '0', $online],
            ),
            IntegrationEventFacts::instant($event->occurredAt()),
            $event->tenantId()->value(),
            $aggregateId,
            $event->externalReference()->value(),
            $stage->value,
            $stage->value,
            $authorised,
            $online,
        );
    }

    private static function stageChanged(EmployerStageChanged $event): EmployerStatusChanged
    {
        $aggregateId = $event->accountId()->value();

        return new EmployerStatusChanged(
            IntegrationEventFacts::id(EmployerStatusChanged::TYPE, $aggregateId, $event->occurredAt(), [$event->from()->value, $event->to()->value]),
            IntegrationEventFacts::instant($event->occurredAt()),
            $event->tenantId()->value(),
            $aggregateId,
            $event->externalReference()->value(),
            $event->from()->value,
            $event->to()->value,
            null,
            null,
        );
    }
}
