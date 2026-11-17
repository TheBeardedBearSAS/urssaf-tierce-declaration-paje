<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Wire;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\CalculationMode;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Estimate\ChildminderEstimateRequest;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Estimate\EstimateRequest;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Estimate\HomeCareEstimateRequest;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Estimate\YoungestChildAge;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Amount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\HalfDays;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\PostalCode;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\EstimateRequestMapper;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\JsonEncoder;

final class EstimateRequestMapperTest extends TestCase
{
    private static function childminder(): ChildminderEstimateRequest
    {
        return ChildminderEstimateRequest::of(
            PostalCode::fromString('75000'),
            PostalCode::fromString('75000'),
            Amount::fromDecimalString('165'),
            1,
            10,
            HalfDays::fromHalfDays(2),
            0,
            0,
        );
    }

    private static function homeCare(): HomeCareEstimateRequest
    {
        return HomeCareEstimateRequest::of(
            PostalCode::fromString('75000'),
            PostalCode::fromString('75000'),
            Amount::fromDecimalString('165'),
            CalculationMode::real(),
            10,
            0,
            0,
            YoungestChildAge::underThree(),
            true,
        );
    }

    public function testTheChildminderOfficialExample(): void
    {
        WireBodyAssertions::assertBodyIsFixture('estimate_ama_request', EstimateRequestMapper::body(self::childminder()));
    }

    public function testTheHomeCareOfficialExample(): void
    {
        WireBodyAssertions::assertBodyIsFixture('estimate_ged_request', EstimateRequestMapper::body(self::homeCare()));
    }

    public function testTheExactBodies(): void
    {
        static::assertSame(
            '{"cdPostalEmployeur":"75000","cdPostalSalarie":"75000","mntSalaireNetMensuel":165.00,"nbEnfantsGardes":1,'
            . '"nbHeuresComplementaires":0,"nbHeuresMajorees":0,"nbJoursActivite":10,"nbJoursCongesPayes":1}',
            JsonEncoder::encode(EstimateRequestMapper::body(self::childminder())),
        );
        static::assertSame(
            '{"cdModeCalcul":"R","cdPostalEmployeur":"75000","cdPostalSalarie":"75000","complementActiviteOk":true,'
            . '"indcEnfantPlusJeuneAgarder":"1","mntSalaireNetMensuel":165.00,"nbHeures":10,"nbHrSupMajA25":0,"nbHrSupMajA50":0}',
            JsonEncoder::encode(EstimateRequestMapper::body(self::homeCare())),
        );
    }

    public function testOptionalValuesThatAreNotSetAreOmittedAndNoPostalCodeIsInjected(): void
    {
        $childminder = ChildminderEstimateRequest::of(null, null, Amount::fromDecimalString('0.5'), 5, 31, null, null, null);
        $homeCare = HomeCareEstimateRequest::of(
            null,
            PostalCode::fromString('97100'),
            Amount::fromDecimalString('9999.99'),
            CalculationMode::specific(),
            1000,
            null,
            40,
            YoungestChildAge::overSix(),
            false,
        );

        static::assertSame(
            '{"mntSalaireNetMensuel":0.50,"nbEnfantsGardes":5,"nbJoursActivite":31}',
            JsonEncoder::encode(EstimateRequestMapper::body($childminder)),
        );
        static::assertSame(
            '{"cdModeCalcul":"S","cdPostalSalarie":"97100","complementActiviteOk":false,"indcEnfantPlusJeuneAgarder":"3",'
            . '"mntSalaireNetMensuel":9999.99,"nbHeures":1000,"nbHrSupMajA50":40}',
            JsonEncoder::encode(EstimateRequestMapper::body($homeCare)),
        );
    }

    public function testTheEmployerPostalCodeAloneIsSent(): void
    {
        $request = ChildminderEstimateRequest::of(PostalCode::fromString('13001'), null, Amount::fromCents(1), 1, 1, null, null, null);

        static::assertSame(
            '{"cdPostalEmployeur":"13001","mntSalaireNetMensuel":0.01,"nbEnfantsGardes":1,"nbJoursActivite":1}',
            JsonEncoder::encode(EstimateRequestMapper::body($request)),
        );
    }

    public function testAnotherRequestIsRefused(): void
    {
        $other = new class implements EstimateRequest {
            public function employerPostalCode(): ?PostalCode
            {
                return null;
            }

            public function employeePostalCode(): ?PostalCode
            {
                return null;
            }

            public function netMonthlySalary(): Amount
            {
                return Amount::fromCents(1);
            }
        };

        try {
            EstimateRequestMapper::body($other);
            static::fail('Expected an InvariantViolation.');
        } catch (InvariantViolation $violation) {
            static::assertSame('estimate_request.unsupported', $violation->violationCode());
            static::assertSame(
                'The estimate request is neither a childminder nor a home childcare request.',
                $violation->getMessage(),
            );
        }
    }
}
