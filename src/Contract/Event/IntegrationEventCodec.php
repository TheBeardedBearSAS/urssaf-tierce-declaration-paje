<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Contract\Event;

use InvalidArgumentException;

/**
 * Encodes an integration event for the outbox and decodes it back (PHP serialization restricted to the event classes,
 * ADR 0012).
 */
final class IntegrationEventCodec
{
    /** @var list<class-string<IntegrationEvent>> */
    public const CLASSES = [
        EmployerStatusChanged::class,
        EmployerForgotten::class,
        MandateRegistered::class,
        MandateCancelled::class,
        DeclarationDeclared::class,
        DeclarationRejected::class,
        DeclarationOutcomeUnknown::class,
        DeclarationAlreadyDeclared::class,
    ];

    public static function encode(IntegrationEvent $event): string
    {
        return serialize($event);
    }

    /**
     * @throws InvalidArgumentException when the data is not an encoded integration event
     */
    public static function decode(string $data): IntegrationEvent
    {
        // @mago-expect analysis:mixed-assignment unserialize() is untyped: the instanceof check below narrows it.
        $event = unserialize($data, ['allowed_classes' => self::CLASSES]);

        return $event instanceof IntegrationEvent ? $event : throw new InvalidArgumentException('The data is not an encoded integration event.');
    }
}
