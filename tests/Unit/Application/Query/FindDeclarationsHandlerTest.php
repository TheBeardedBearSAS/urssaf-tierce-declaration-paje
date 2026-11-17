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
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\DeclarationView;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Query\FindDeclarations;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Query\FindDeclarationsHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Fake\DeclarationWorld;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Declaration\RecordSamples;

final class FindDeclarationsHandlerTest extends TestCase
{
    public function testByExternalReferencesKeepsTheOrderOfTheQueryAndSkipsUnknownOnes(): void
    {
        $world = $this->world();

        $views = $this->find($world, FindDeclarations::byExternalReferences(
            DeclarationWorld::tenant(),
            ExternalReference::fromString('ref-2'),
            ExternalReference::fromString('unknown'),
            ExternalReference::fromString('ref-1'),
        ));

        static::assertSame(['record-2', 'record-1'], self::ids($views));
    }

    public function testByRecordIdsKeepsTheOrderAndSkipsUnknownOnesAndOtherTenants(): void
    {
        $world = $this->world();

        $views = $this->find($world, FindDeclarations::byRecordIds(
            DeclarationWorld::tenant(),
            DeclarationRecordId::fromString('record-3'),
            DeclarationRecordId::fromString('unknown'),
            DeclarationRecordId::fromString('record-9'),
            DeclarationRecordId::fromString('record-1'),
        ));

        static::assertSame(['record-3', 'record-1'], self::ids($views));
    }

    public function testNothingRequestedReturnsNothing(): void
    {
        static::assertSame([], $this->find($this->world(), FindDeclarations::byRecordIds(DeclarationWorld::tenant())));
        static::assertSame([], $this->find($this->world(), FindDeclarations::byExternalReferences(DeclarationWorld::tenant())));
    }

    public function testInStateListsTheRecordsOfTheTenantInTheOrderOfTheRepository(): void
    {
        $world = $this->world();

        $views = $this->find($world, FindDeclarations::inState(DeclarationWorld::tenant(), DeclarationState::Pending, null));

        static::assertSame(['record-1', 'record-2'], self::ids($views));
    }

    public function testInStateCapsTheLimitWithThePolicy(): void
    {
        $world = new DeclarationWorld((new WorkflowPolicy())->withPageSizes(1, 2));
        foreach ([1, 2, 3, 4] as $number) {
            $world->recordWith('record-' . $number, DeclarationState::Pending, 0, RecordSamples::childminder(), 'ref-' . $number, 'tenant-1', 'Y1869005780009');
        }

        static::assertCount(1, $this->find($world, FindDeclarations::inState(DeclarationWorld::tenant(), DeclarationState::Pending, null)));
        static::assertCount(2, $this->find($world, FindDeclarations::inState(DeclarationWorld::tenant(), DeclarationState::Pending, 10)));
        static::assertCount(1, $this->find($world, FindDeclarations::inState(DeclarationWorld::tenant(), DeclarationState::Pending, 1)));
    }

    public function testTheNamedConstructorsFillTheQuery(): void
    {
        $tenant = DeclarationWorld::tenant();
        $reference = ExternalReference::fromString('ref-1');
        $id = DeclarationRecordId::fromString('record-1');

        $byReferences = FindDeclarations::byExternalReferences($tenant, ...['a' => $reference]);
        $byIds = FindDeclarations::byRecordIds($tenant, ...['a' => $id]);
        $inState = FindDeclarations::inState($tenant, DeclarationState::Error, 3);

        static::assertSame([$reference], $byReferences->externalReferences);
        static::assertSame([], $byReferences->recordIds);
        static::assertNull($byReferences->state);
        static::assertNull($byReferences->limit);
        static::assertSame([], $byIds->externalReferences);
        static::assertSame([$id], $byIds->recordIds);
        static::assertNull($byIds->state);
        static::assertSame([], $inState->externalReferences);
        static::assertSame([], $inState->recordIds);
        static::assertSame(DeclarationState::Error, $inState->state);
        static::assertSame(3, $inState->limit);
    }

    private function world(): DeclarationWorld
    {
        $world = new DeclarationWorld();
        $world->recordWith('record-1', DeclarationState::Pending, 0, RecordSamples::childminder(), 'ref-1', 'tenant-1', 'Y1869005780009');
        $world->recordWith('record-2', DeclarationState::Pending, 0, RecordSamples::childminder(), 'ref-2', 'tenant-1', 'Y1869005780009');
        $world->recordWith('record-3', DeclarationState::Declared, 1, RecordSamples::childminder(), 'ref-3', 'tenant-1', 'Y1869005780009');
        $world->recordWith('record-9', DeclarationState::Pending, 0, RecordSamples::childminder(), 'ref-9', 'tenant-2', 'Y1869005780009');

        return $world;
    }

    /**
     * @param list<DeclarationView> $views
     *
     * @return list<string>
     */
    private static function ids(array $views): array
    {
        return array_map(static fn(DeclarationView $view): string => $view->recordId, $views);
    }

    /**
     * @return list<DeclarationView>
     */
    private function find(DeclarationWorld $world, FindDeclarations $query): array
    {
        return (new FindDeclarationsHandler($world->records, $world->policy))($query);
    }
}
