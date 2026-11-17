<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Query;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\DeclarationSelector;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Query\GetDeclaration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Query\GetDeclarationHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Fake\DeclarationWorld;

final class GetDeclarationHandlerTest extends TestCase
{
    public function testItReturnsTheViewOfTheRecord(): void
    {
        $world = new DeclarationWorld();
        $world->record('record-1', DeclarationState::Declared);
        $handler = new GetDeclarationHandler($world->locator);

        $byId = $handler(new GetDeclaration(DeclarationWorld::tenant(), DeclarationSelector::byRecordId(DeclarationRecordId::fromString('record-1'))));
        $byReference = $handler(new GetDeclaration(DeclarationWorld::tenant(), DeclarationSelector::byExternalReference(DeclarationWorld::reference())));

        static::assertSame('record-1', $byId->recordId);
        static::assertSame('declared', $byId->status->state);
        static::assertEquals($byId, $byReference);
    }

    public function testAnUnknownRecordIsNotFound(): void
    {
        $handler = new GetDeclarationHandler((new DeclarationWorld())->locator);

        $this->expectException(RecordNotFound::class);
        $handler(new GetDeclaration(DeclarationWorld::tenant(), DeclarationSelector::byExternalReference(DeclarationWorld::reference())));
    }
}
