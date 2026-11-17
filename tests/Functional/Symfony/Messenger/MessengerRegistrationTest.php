<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Messenger;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\EmployerSelector;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Query\GetEmployerAccount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Query\GetEmployerAccountHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Message\SyncEmployerStatusesMessage;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Message\SyncEmployerStatusMessage;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\MessageHandler\SyncEmployerStatusesMessageHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\MessageHandler\SyncEmployerStatusMessageHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Support\HttpScript;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Support\Scenario;

final class MessengerRegistrationTest extends TestCase
{
    private const MESSENGER = ['messenger' => ['enabled' => true]];

    public function testNothingIsRegisteredWhenMessengerIsDisabled(): void
    {
        $harness = Harness::boot();

        static::assertFalse($harness->hasPrivate(SyncEmployerStatusesMessageHandler::class));
        static::assertFalse($harness->hasPrivate(SyncEmployerStatusMessageHandler::class));
    }

    public function testBothHandlersAreRegistered(): void
    {
        $harness = Harness::boot(self::MESSENGER, true);

        static::assertTrue($harness->hasPrivate(SyncEmployerStatusesMessageHandler::class));
        static::assertTrue($harness->hasPrivate(SyncEmployerStatusMessageHandler::class));
    }

    public function testTheMessagesAreRoutedToTheHandlersThroughTheBus(): void
    {
        $harness = Harness::boot(self::MESSENGER, true);
        $bus = $harness->privateService('messenger.default_bus', MessageBusInterface::class);

        $bus->dispatch(new SyncEmployerStatusesMessage('tenant-1'));
        $bus->dispatch(new SyncEmployerStatusMessage('tenant-1', 'account-1'));

        $sent = $harness->privateService('messenger.transport.async', InMemoryTransport::class)->getSent();
        static::assertCount(2, $sent);
        static::assertInstanceOf(SyncEmployerStatusesMessage::class, $sent[0]->getMessage());
        static::assertInstanceOf(SyncEmployerStatusMessage::class, $sent[1]->getMessage());
    }

    public function testTheTenantMessageRefreshesTheEmployersOfTheTenant(): void
    {
        $harness = Harness::boot(self::MESSENGER, true);
        $account = Scenario::importedEmployer($harness);
        static::assertNull(self::lastChecked($harness, $account->accountId));

        $harness->privateService(SyncEmployerStatusesMessageHandler::class, SyncEmployerStatusesMessageHandler::class)(new SyncEmployerStatusesMessage('tenant-1'));

        static::assertSame(1, $harness->urssaf->calls(HttpScript::ROUTE_020));
        static::assertNotNull(self::lastChecked($harness, $account->accountId));
    }

    public function testTheAccountMessageRefreshesOneEmployer(): void
    {
        $harness = Harness::boot(self::MESSENGER, true);
        $account = Scenario::importedEmployer($harness);

        $harness->privateService(SyncEmployerStatusMessageHandler::class, SyncEmployerStatusMessageHandler::class)(new SyncEmployerStatusMessage('tenant-1', $account->accountId));

        static::assertSame(1, $harness->urssaf->calls(HttpScript::ROUTE_020));
        static::assertNotNull(self::lastChecked($harness, $account->accountId));
    }

    public function testAnUnknownAccountIsUnrecoverable(): void
    {
        $harness = Harness::boot(self::MESSENGER, true);

        $this->expectException(UnrecoverableMessageHandlingException::class);

        $harness->privateService(SyncEmployerStatusMessageHandler::class, SyncEmployerStatusMessageHandler::class)(new SyncEmployerStatusMessage('tenant-1', 'unknown-account'));
    }

    public function testTheSerialisedMessagesContainIdentifiersOnly(): void
    {
        $tenant = serialize(new SyncEmployerStatusesMessage('tenant-1'));
        $account = serialize(new SyncEmployerStatusMessage('tenant-1', 'account-1'));

        foreach ([$tenant, $account] as $payload) {
            static::assertStringNotContainsString('1975-01-30', $payload);
            static::assertStringContainsString('tenant-1', $payload);
        }
        // @mago-expect analysis:mixed-assignment unserialize() is untyped: the assertion below narrows it.
        $restored = unserialize($account);
        static::assertInstanceOf(SyncEmployerStatusMessage::class, $restored);
        static::assertSame(['tenantId' => 'tenant-1', 'employerAccountId' => 'account-1'], get_object_vars($restored));
    }

    private static function lastChecked(Harness $harness, string $accountId): ?string
    {
        $view = $harness->get(GetEmployerAccountHandler::class)(new GetEmployerAccount(
            TenantId::fromString('tenant-1'),
            EmployerSelector::byAccountId(EmployerAccountId::fromString($accountId)),
        ));

        return $view->lastStatus?->checkedAt;
    }
}
