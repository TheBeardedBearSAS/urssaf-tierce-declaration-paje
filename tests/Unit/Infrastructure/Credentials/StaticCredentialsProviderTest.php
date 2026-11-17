<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Credentials;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Credentials;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Credentials\StaticCredentialsProvider;

final class StaticCredentialsProviderTest extends TestCase
{
    public function testReturnsTheCredentialsOfTheTenant(): void
    {
        $credentials = Credentials::of('client-a', 'secret-a');
        $provider = new StaticCredentialsProvider([
            'tenant-a' => $credentials,
            'tenant-b' => Credentials::of('client-b', 'secret-b'),
        ]);

        static::assertSame($credentials, $provider->forTenant(TenantId::fromString('tenant-a')));
    }

    public function testUnknownTenantIsRecordNotFound(): void
    {
        $provider = new StaticCredentialsProvider([
            'tenant-a' => Credentials::of('client-a', 'secret-a'),
        ]);

        $this->expectExceptionObject(RecordNotFound::withIdentifier('credentials', 'tenant-z'));

        $provider->forTenant(TenantId::fromString('tenant-z'));
    }

    public function testNoCredentialsAtAllIsRecordNotFound(): void
    {
        $provider = new StaticCredentialsProvider([]);

        $this->expectExceptionObject(RecordNotFound::withIdentifier('credentials', 'tenant-a'));

        $provider->forTenant(TenantId::fromString('tenant-a'));
    }

    public function testUnknownTenantErrorCarriesTheDocumentedCode(): void
    {
        $provider = new StaticCredentialsProvider([]);

        try {
            $provider->forTenant(TenantId::fromString('tenant-a'));
            static::fail('The tenant has no credentials.');
        } catch (RecordNotFound $exception) {
            static::assertSame('credentials.not_found', $exception->violationCode());
        }
    }
}
