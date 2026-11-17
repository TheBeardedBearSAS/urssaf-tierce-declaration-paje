<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Wire;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\DeclarationRequestMapper;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\EmployerQueryMapper;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\QueryEncoding;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\EmployeeWireSamples;

final class EmployerQueryMapperTest extends TestCase
{
    public function testTheBirthDateIsTheOnlyQueryParameter(): void
    {
        $query = EmployerQueryMapper::query(EmployeeWireSamples::employer());

        static::assertSame(['dtNaissanceEmployeur' => '1975-02-23'], $query);
        static::assertSame('dtNaissanceEmployeur=1975-02-23', QueryEncoding::build($query));
    }

    public function testTheDeclarationRequestDelegatesItsQueryToIt(): void
    {
        static::assertSame(
            EmployerQueryMapper::query(EmployeeWireSamples::employer()),
            DeclarationRequestMapper::query(EmployeeWireSamples::employer()),
        );
    }
}
