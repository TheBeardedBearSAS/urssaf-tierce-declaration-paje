<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Service;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\DeclarationSelector;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Fake\DeclarationWorld;

final class DeclarationLocatorTest extends TestCase
{
    public function testItLocatesARecordByIdOrByExternalReference(): void
    {
        $world = new DeclarationWorld();
        $world->record('record-1', DeclarationState::Pending, 0);

        $byId = $world->locator->locate(DeclarationWorld::tenant(), DeclarationSelector::byRecordId(DeclarationRecordId::fromString('record-1')));
        $byReference = $world->locator->locate(DeclarationWorld::tenant(), DeclarationSelector::byExternalReference(DeclarationWorld::reference()));

        static::assertSame('record-1', $byId->id()->value());
        static::assertSame('record-1', $byReference->id()->value());
    }

    public function testAnUnknownExternalReferenceIsNotFound(): void
    {
        $world = new DeclarationWorld();

        $this->expectException(RecordNotFound::class);
        $this->expectExceptionMessage('No declaration_record found for identifier "unknown".');
        $world->locator->locate(DeclarationWorld::tenant(), DeclarationSelector::byExternalReference(ExternalReference::fromString('unknown')));
    }

    public function testAnUnknownRecordIdIsNotFound(): void
    {
        $world = new DeclarationWorld();

        $this->expectException(RecordNotFound::class);
        $world->locator->locate(DeclarationWorld::tenant(), DeclarationSelector::byRecordId(DeclarationRecordId::fromString('unknown')));
    }

    public function testARecordOfAnotherTenantIsNotFoundWhateverTheSelector(): void
    {
        $world = new DeclarationWorld();
        $world->record('record-1', DeclarationState::Pending, 0);
        $other = DeclarationWorld::tenant('tenant-2');

        try {
            $world->locator->locate($other, DeclarationSelector::byRecordId(DeclarationRecordId::fromString('record-1')));
            static::fail('Expected a RecordNotFound.');
        } catch (RecordNotFound $notFound) {
            static::assertSame('record-1', $notFound->identifier());
        }
        try {
            $world->locator->locate($other, DeclarationSelector::byExternalReference(DeclarationWorld::reference()));
            static::fail('Expected a RecordNotFound.');
        } catch (RecordNotFound $notFound) {
            static::assertSame('decl-ref-1', $notFound->identifier());
        }
    }
}
