<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Contract\Event;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use TheBeardedBear\UrssafTierceDeclarationPaje\Contract\Event\DeclarationAlreadyDeclared;
use TheBeardedBear\UrssafTierceDeclarationPaje\Contract\Event\DeclarationDeclared;
use TheBeardedBear\UrssafTierceDeclarationPaje\Contract\Event\DeclarationOutcomeUnknown;
use TheBeardedBear\UrssafTierceDeclarationPaje\Contract\Event\DeclarationRejected;
use TheBeardedBear\UrssafTierceDeclarationPaje\Contract\Event\EmployerForgotten;
use TheBeardedBear\UrssafTierceDeclarationPaje\Contract\Event\EmployerStatusChanged;
use TheBeardedBear\UrssafTierceDeclarationPaje\Contract\Event\IntegrationEvent;
use TheBeardedBear\UrssafTierceDeclarationPaje\Contract\Event\IntegrationEventCodec;
use TheBeardedBear\UrssafTierceDeclarationPaje\Contract\Event\MandateCancelled;
use TheBeardedBear\UrssafTierceDeclarationPaje\Contract\Event\MandateRegistered;

final class IntegrationEventCodecTest extends TestCase
{
    #[DataProvider('provideEvents')]
    public function testAnEventIsDecodedAsEncoded(IntegrationEvent $event): void
    {
        static::assertEquals($event, IntegrationEventCodec::decode(IntegrationEventCodec::encode($event)));
        static::assertContains($event::class, IntegrationEventCodec::CLASSES);
    }

    /**
     * @return iterable<string, array{IntegrationEvent}>
     */
    public static function provideEvents(): iterable
    {
        yield 'employer status changed' => [new EmployerStatusChanged('e', '2026-11-15T10:00:00Z', 't', 'a', 'r', 'tracked', 'mandate_registered', null, null)];
        yield 'employer forgotten' => [new EmployerForgotten('e', '2026-11-15T10:00:00Z', 't', 'a', 'r', ['m1', 'm2'], ['r1'])];
        yield 'mandate registered' => [new MandateRegistered('e', '2026-11-15T10:00:00Z', 't', 'a', 'r', 'x', 'registered')];
        yield 'mandate cancelled' => [new MandateCancelled('e', '2026-11-15T10:00:00Z', 't', 'a', 'r', 'x')];
        yield 'declaration declared' => [new DeclarationDeclared('e', '2026-11-15T10:00:00Z', 't', 'a', 'r', null, 'home_care', '2026-10-01', '2026-10-31', 'd', [])];
        yield 'declaration declared without reference' => [new DeclarationDeclared('e', '2026-11-15T10:00:00Z', 't', 'a', 'r', 'x', 'home_care', '2026-10-01', '2026-10-31', null, [])];
        yield 'declaration rejected' => [new DeclarationRejected('e', '2026-11-15T10:00:00Z', 't', 'a', 'r', 'x', 'childminder', '2026-10-01', '2026-10-31', ['ERR_A'])];
        yield 'declaration outcome unknown' => [new DeclarationOutcomeUnknown('e', '2026-11-15T10:00:00Z', 't', 'a', 'r', 'x', 'childminder', '2026-10-01', '2026-10-31', 'stale_submission')];
        yield 'declaration already declared' => [new DeclarationAlreadyDeclared('e', '2026-11-15T10:00:00Z', 't', 'a', 'r', 'x', 'childminder', '2026-10-01', '2026-10-31', [])];
    }

    #[DataProvider('provideForeignData')]
    public function testOnlyEncodedIntegrationEventsAreDecoded(string $data): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The data is not an encoded integration event.');

        IntegrationEventCodec::decode($data);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideForeignData(): iterable
    {
        yield 'text' => [serialize('text')];
        yield 'foreign object' => [serialize(new stdClass())];
    }

    public function testNoOtherClassIsInstantiatedWhileDecoding(): void
    {
        try {
            IntegrationEventCodec::decode(serialize(new WakeUpRecorder()));
            static::fail('The data should be refused.');
        } catch (InvalidArgumentException $failure) {
            static::assertSame('The data is not an encoded integration event.', $failure->getMessage());
        }

        static::assertFalse(WakeUpRecorder::wokenUp());
    }
}
