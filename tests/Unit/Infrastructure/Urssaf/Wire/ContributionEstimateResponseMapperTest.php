<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Wire;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\ContributionEstimateResponseMapper;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\JsonDecoder;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\Fixtures;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\WireAssertions;

final class ContributionEstimateResponseMapperTest extends TestCase
{
    public function testTheOfficialExampleIsMapped(): void
    {
        $estimate = ContributionEstimateResponseMapper::map(JsonDecoder::decode(Fixtures::load('estimate_response')));

        static::assertSame('0.00', $estimate->coveredByFamilyBenefits()?->toDecimalString());
        static::assertSame('0.00', $estimate->employerShare()?->toDecimalString());
        static::assertSame('0.00', $estimate->employerExemption()?->toDecimalString());
        static::assertCount(1, $estimate->messages());
        static::assertSame('ER_API_020_NB_0000', $estimate->messages()[0]->code());
    }

    public function testEveryFieldIsReadFromItsWireName(): void
    {
        $estimate = ContributionEstimateResponseMapper::map(JsonDecoder::decode(
            '{"mntCotiPriseEnChargeOdpf":123.45,"mntCotiEmplAcharge":6.7,"mntExoEmployeur":-8}',
        ));

        static::assertSame('123.45', $estimate->coveredByFamilyBenefits()?->toDecimalString());
        static::assertSame('6.70', $estimate->employerShare()?->toDecimalString());
        static::assertSame('-8.00', $estimate->employerExemption()?->toDecimalString());
        static::assertSame([], $estimate->messages());
    }

    public function testAbsentFieldsAreNull(): void
    {
        foreach ([JsonDecoder::decode('{}'), JsonDecoder::decode('{"mntCotiPriseEnChargeOdpf":null}')] as $payload) {
            $estimate = ContributionEstimateResponseMapper::map($payload);

            static::assertNull($estimate->coveredByFamilyBenefits());
            static::assertNull($estimate->employerShare());
            static::assertNull($estimate->employerExemption());
            static::assertSame([], $estimate->messages());
        }
    }

    public function testAMalformedAmountIsRefusedNamingTheFieldOnly(): void
    {
        foreach (['mntCotiPriseEnChargeOdpf', 'mntCotiEmplAcharge', 'mntExoEmployeur'] as $field) {
            WireAssertions::assertUnexpected(
                'Unexpected URSSAF response: field "' . $field . '" is not a number.',
                static fn(): mixed => ContributionEstimateResponseMapper::map(JsonDecoder::decode('{"' . $field . '":"12,5"}')),
            );
        }
    }

    public function testAMalformedMessageListIsReadLeniently(): void
    {
        static::assertSame([], ContributionEstimateResponseMapper::map(JsonDecoder::decode('{"erreursNonBloquantes":[1]}'))->messages());
    }

    public function testWhatIsNotAJsonObjectIsRefused(): void
    {
        WireAssertions::assertRefusesWhatIsNotAnObject(ContributionEstimateResponseMapper::map(...));
    }
}
