<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Wire;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\HomeCareContractEnd;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\HomeCareContractEndMapper;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\JsonEncoder;

final class HomeCareContractEndMapperTest extends TestCase
{
    public function testTheOfficialExampleIsEncodedWithTheSwaggerName(): void
    {
        $zero = DeclarationWireSamples::amount('0');

        foreach (['predeclare_ged_request_valid', 'declare_ged_request_valid'] as $fixture) {
            WireBodyAssertions::assertBodyIsFixtureSection($fixture, 'finDeContrat', HomeCareContractEndMapper::encode(HomeCareContractEnd::of($zero, $zero, $zero, $zero, $zero)));
        }
    }

    public function testEveryIndemnityHasItsWireName(): void
    {
        $contractEnd = HomeCareContractEnd::of(
            DeclarationWireSamples::amount('1'),
            DeclarationWireSamples::amount('2.5'),
            DeclarationWireSamples::amount('3'),
            DeclarationWireSamples::amount('4'),
            DeclarationWireSamples::amount('5.05'),
        );

        static::assertSame(
            '{"mntIndDepartRetraite":1.00,"mntIndCcp":2.50,"mntIndPreavis":3.00,"mntIndPrecarite":4.00,"mntIndLicenciement":5.05}',
            JsonEncoder::encode(HomeCareContractEndMapper::encode($contractEnd)),
        );
    }

    public function testNoIndemnityGivesAnEmptyObjectNeverAList(): void
    {
        static::assertSame(
            '{}',
            JsonEncoder::encode(HomeCareContractEndMapper::encode(HomeCareContractEnd::of(null, null, null, null, null))),
        );
    }
}
