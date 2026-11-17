<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Employee;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\ActivityType;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\Address;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\AdministrativeIdentity;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeAccount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeAssociation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeCreation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\Nir;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\OnlineAccountState;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Message\NonBlockingMessage;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\PostalCode;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\DomainAssertions;

final class EmployeeResultsTest extends TestCase
{
    public function testCreationKeepsEveryField(): void
    {
        $type = ActivityType::childminder();
        $birthDate = CalendarDate::fromString('1980-01-30');
        $nir = Nir::of('178010701005265');
        $licenceStart = CalendarDate::fromString('2020-09-01');
        $address = Address::of(null, null, null, null, null, null, '94022', PostalCode::fromString('94600'));
        $identity = AdministrativeIdentity::of(null, null, null, null, null, null, null, null);

        $creation = EmployeeCreation::of($type, $birthDate, $nir, $licenceStart, $address, $identity);

        static::assertSame($type, $creation->activityType());
        static::assertSame($birthDate, $creation->birthDate());
        static::assertSame($nir, $creation->nir());
        static::assertSame($licenceStart, $creation->licenceStartDate());
        static::assertSame($address, $creation->address());
        static::assertSame($identity, $creation->identity());
        static::assertFalse($creation->isEmpty());
    }

    public function testCreationIsEmptyOnlyWithoutAnyField(): void
    {
        $date = CalendarDate::fromString('1980-01-30');

        static::assertTrue(EmployeeCreation::of(null, null, null, null, null, null)->isEmpty());
        static::assertFalse(EmployeeCreation::of(ActivityType::homeCare(), null, null, null, null, null)->isEmpty());
        static::assertFalse(EmployeeCreation::of(null, $date, null, null, null, null)->isEmpty());
        static::assertFalse(EmployeeCreation::of(null, null, Nir::of('178010701005265'), null, null, null)->isEmpty());
        static::assertFalse(EmployeeCreation::of(null, null, null, $date, null, null)->isEmpty());
        static::assertFalse(EmployeeCreation::of(null, null, null, null, Address::of(null, null, null, null, null, null, '94022', PostalCode::fromString('94600')), null)->isEmpty());
        static::assertFalse(EmployeeCreation::of(null, null, null, null, null, AdministrativeIdentity::of(null, null, null, null, null, null, null, null))->isEmpty());
    }

    public function testCreationIsMaskedInDebugOutput(): void
    {
        $dump = print_r(EmployeeCreation::of(null, null, Nir::of('178010701005265'), null, null, null), true);

        static::assertStringNotContainsString('178010701005265', $dump);
        static::assertStringContainsString('***', $dump);
    }

    public function testAccountKeepsEveryField(): void
    {
        $state = OnlineAccountState::of('Actif');

        $account = EmployeeAccount::of('Y2926822480004', true, false, $state);

        static::assertSame('Y2926822480004', $account->employeeNumber());
        static::assertTrue($account->childminder());
        static::assertFalse($account->homeCare());
        static::assertSame($state, $account->state());
        static::assertNull(EmployeeAccount::of(null, false, true, $state)->employeeNumber());
        static::assertTrue(EmployeeAccount::of(null, false, true, $state)->homeCare());
        static::assertFalse(EmployeeAccount::of(null, false, true, $state)->childminder());
    }

    public function testAccountMasksTheEmployeeNumberInDebugOutput(): void
    {
        $dump = print_r(EmployeeAccount::of('Y2926822480004', true, false, OnlineAccountState::of('Actif')), true);

        static::assertStringNotContainsString('Y2926822480004', $dump);
        static::assertStringContainsString('Actif', $dump);
    }

    public function testAccountDebugInfoIsExact(): void
    {
        static::assertSame(
            ['employeeNumber' => '***', 'childminder' => true, 'homeCare' => false, 'state' => 'Actif'],
            EmployeeAccount::of('Y2926822480004', true, false, OnlineAccountState::of('Actif'))->__debugInfo(),
        );
    }

    public function testAssociationKeepsEveryField(): void
    {
        $account = EmployeeAccount::of(null, true, true, OnlineAccountState::of('Actif'));
        $messages = [NonBlockingMessage::of('ER_API_023_NB_0000', null, null)];

        $association = EmployeeAssociation::of(true, false, true, $account, $messages);

        static::assertTrue($association->created());
        static::assertFalse($association->associated());
        static::assertTrue($association->alreadyAssociated());
        static::assertSame($account, $association->account());
        static::assertSame($messages, $association->messages());

        $other = EmployeeAssociation::of(false, true, false, null, []);

        static::assertFalse($other->created());
        static::assertTrue($other->associated());
        static::assertFalse($other->alreadyAssociated());
        static::assertNull($other->account());
        static::assertSame([], $other->messages());
    }

    public function testCreationDatesAreSensitive(): void
    {
        DomainAssertions::assertSensitiveParameters(EmployeeCreation::class, 'of', ['birthDate', 'licenceStartDate']);
        DomainAssertions::assertSensitiveParameters(EmployeeCreation::class, '__construct', ['birthDate', 'licenceStartDate']);
    }
}
