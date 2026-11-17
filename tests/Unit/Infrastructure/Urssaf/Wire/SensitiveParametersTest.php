<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Wire;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\AddressMapper;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\AdministrativeIdentityMapper;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\AssociateEmployeeRequestMapper;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\ChildMapper;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\ChildminderChildMapper;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\ChildminderContractEndMapper;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\DeclarationRequestMapper;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\DeclaredEmployeeMapper;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\EmployeeCreationMapper;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\EmployeeIdentificationQueryMapper;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\EmployerQueryMapper;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\JsonEncoder;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\MandateRequestMapper;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\OptionalWireValues;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\Payload;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\QueryEncoding;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\VerifyChildrenRequestMapper;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\VerifyEmployeeRequestMapper;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\VerifyEmployerRequestMapper;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\DomainAssertions;

/**
 * Every wire entry point that receives personal data (a domain object or the raw request array) redacts it in a stack
 * trace (ADR 0007).
 */
final class SensitiveParametersTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string, string, list<string>}>
     */
    public static function provideSensitiveEntryPoints(): iterable
    {
        yield 'JsonEncoder::encode' => [JsonEncoder::class, 'encode', ['value']];
        yield 'JsonEncoder::object' => [JsonEncoder::class, 'object', ['value']];
        yield 'JsonEncoder::literal' => [JsonEncoder::class, 'literal', ['value']];
        yield 'JsonEncoder::encodeItem' => [JsonEncoder::class, 'encodeItem', ['item']];
        yield 'QueryEncoding::build' => [QueryEncoding::class, 'build', ['parameters']];
        yield 'OptionalWireValues::present' => [OptionalWireValues::class, 'present', ['members']];
        yield 'Payload::__construct' => [Payload::class, '__construct', ['data']];
        yield 'Payload::fromDecoded' => [Payload::class, 'fromDecoded', ['decoded']];
        yield 'AddressMapper::encode' => [AddressMapper::class, 'encode', ['address']];
        yield 'AdministrativeIdentityMapper::encode' => [AdministrativeIdentityMapper::class, 'encode', ['identity']];
        yield 'AssociateEmployeeRequestMapper::query' => [AssociateEmployeeRequestMapper::class, 'query', ['employer', 'identification']];
        yield 'ChildMapper::encode' => [ChildMapper::class, 'encode', ['child']];
        yield 'ChildminderChildMapper::encode' => [ChildminderChildMapper::class, 'encode', ['child']];
        yield 'ChildminderContractEndMapper::encode' => [ChildminderContractEndMapper::class, 'encode', ['contractEnd']];
        yield 'DeclarationRequestMapper::body' => [DeclarationRequestMapper::class, 'body', ['declaration']];
        yield 'DeclarationRequestMapper::query' => [DeclarationRequestMapper::class, 'query', ['employer']];
        yield 'DeclaredEmployeeMapper::encode' => [DeclaredEmployeeMapper::class, 'encode', ['employee']];
        yield 'EmployeeCreationMapper::encode' => [EmployeeCreationMapper::class, 'encode', ['creation']];
        yield 'EmployeeIdentificationQueryMapper::query' => [EmployeeIdentificationQueryMapper::class, 'query', ['identification']];
        yield 'EmployerQueryMapper::query' => [EmployerQueryMapper::class, 'query', ['employer']];
        yield 'MandateRequestMapper::query' => [MandateRequestMapper::class, 'query', ['employer']];
        yield 'VerifyChildrenRequestMapper::query' => [VerifyChildrenRequestMapper::class, 'query', ['employer']];
        yield 'VerifyChildrenRequestMapper::body' => [VerifyChildrenRequestMapper::class, 'body', ['children']];
        yield 'VerifyEmployeeRequestMapper::query' => [VerifyEmployeeRequestMapper::class, 'query', ['identification']];
        yield 'VerifyEmployerRequestMapper::query' => [VerifyEmployerRequestMapper::class, 'query', ['employer']];
    }

    /**
     * @param class-string $class
     * @param list<string> $expected
     */
    #[DataProvider('provideSensitiveEntryPoints')]
    public function testTheEntryPointRedactsItsPersonalData(string $class, string $method, array $expected): void
    {
        DomainAssertions::assertSensitiveParameters($class, $method, $expected);
    }
}
