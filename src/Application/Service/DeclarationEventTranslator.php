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

use TheBeardedBear\UrssafTierceDeclarationPaje\Contract\Event\DeclarationAlreadyDeclared;
use TheBeardedBear\UrssafTierceDeclarationPaje\Contract\Event\DeclarationDeclared;
use TheBeardedBear\UrssafTierceDeclarationPaje\Contract\Event\DeclarationOutcomeUnknown;
use TheBeardedBear\UrssafTierceDeclarationPaje\Contract\Event\DeclarationRejected;
use TheBeardedBear\UrssafTierceDeclarationPaje\Contract\Event\IntegrationEvent;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\Event\DeclarationAlreadyDeclared as DomainDeclarationAlreadyDeclared;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\Event\DeclarationDeclared as DomainDeclarationDeclared;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\Event\DeclarationOutcomeUnknown as DomainDeclarationOutcomeUnknown;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\Event\DeclarationRejected as DomainDeclarationRejected;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\DomainEvent;

/**
 * Integration events of the declaration workflow (ADR 0012). Recorded, requeued, failed and rejected-credentials
 * declarations have none: they are not final facts for the host. The employer account id is null when the employer was not
 * tracked.
 */
final class DeclarationEventTranslator
{
    /**
     * @return list<IntegrationEvent> the integration event of the domain event, none when it has none
     */
    public static function translate(DomainEvent $event): array
    {
        return match (true) {
            $event instanceof DomainDeclarationDeclared => [self::declared($event)],
            $event instanceof DomainDeclarationRejected => [self::rejected($event)],
            $event instanceof DomainDeclarationOutcomeUnknown => [self::outcomeUnknown($event)],
            $event instanceof DomainDeclarationAlreadyDeclared => [self::alreadyDeclared($event)],
            default => [],
        };
    }

    private static function declared(DomainDeclarationDeclared $event): DeclarationDeclared
    {
        $aggregateId = $event->recordId()->value();
        $reference = $event->documentReference();

        return new DeclarationDeclared(
            IntegrationEventFacts::id(DeclarationDeclared::TYPE, $aggregateId, $event->occurredAt(), [$reference ?? '', ...$event->nonBlockingCodes()]),
            IntegrationEventFacts::instant($event->occurredAt()),
            $event->tenantId()->value(),
            $aggregateId,
            $event->externalReference()->value(),
            $event->employerAccountId()?->value(),
            $event->activity()->value,
            $event->period()->start()->toString(),
            $event->period()->end()->toString(),
            $reference,
            $event->nonBlockingCodes(),
        );
    }

    private static function rejected(DomainDeclarationRejected $event): DeclarationRejected
    {
        $aggregateId = $event->recordId()->value();
        $codes = $event->errorCodes();

        return new DeclarationRejected(
            IntegrationEventFacts::id(DeclarationRejected::TYPE, $aggregateId, $event->occurredAt(), $codes),
            IntegrationEventFacts::instant($event->occurredAt()),
            $event->tenantId()->value(),
            $aggregateId,
            $event->externalReference()->value(),
            $event->employerAccountId()?->value(),
            $event->activity()->value,
            $event->period()->start()->toString(),
            $event->period()->end()->toString(),
            $codes,
        );
    }

    private static function outcomeUnknown(DomainDeclarationOutcomeUnknown $event): DeclarationOutcomeUnknown
    {
        $aggregateId = $event->recordId()->value();
        $reason = $event->reason()->value;

        return new DeclarationOutcomeUnknown(
            IntegrationEventFacts::id(DeclarationOutcomeUnknown::TYPE, $aggregateId, $event->occurredAt(), [$reason]),
            IntegrationEventFacts::instant($event->occurredAt()),
            $event->tenantId()->value(),
            $aggregateId,
            $event->externalReference()->value(),
            $event->employerAccountId()?->value(),
            $event->activity()->value,
            $event->period()->start()->toString(),
            $event->period()->end()->toString(),
            $reason,
        );
    }

    private static function alreadyDeclared(DomainDeclarationAlreadyDeclared $event): DeclarationAlreadyDeclared
    {
        $aggregateId = $event->recordId()->value();
        $codes = $event->errorCodes();

        return new DeclarationAlreadyDeclared(
            IntegrationEventFacts::id(DeclarationAlreadyDeclared::TYPE, $aggregateId, $event->occurredAt(), $codes),
            IntegrationEventFacts::instant($event->occurredAt()),
            $event->tenantId()->value(),
            $aggregateId,
            $event->externalReference()->value(),
            $event->employerAccountId()?->value(),
            $event->activity()->value,
            $event->period()->start()->toString(),
            $event->period()->end()->toString(),
            $codes,
        );
    }
}
