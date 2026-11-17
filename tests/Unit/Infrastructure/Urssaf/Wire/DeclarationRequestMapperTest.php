<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Wire;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\CalculationMode;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\ChildminderChild;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\ChildminderDeclaration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\Declaration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationActivity;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationCommon;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\HomeCareDeclaration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\DeclaredEmployee;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeByNir;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\Nir;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\DeclarationRequestMapper;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\JsonEncoder;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\Fixtures;

final class DeclarationRequestMapperTest extends TestCase
{
    public function testTheChildminderDeclarationOfTheOfficialExample(): void
    {
        WireBodyAssertions::assertBodyIsFixture(
            'declare_ama_request_valid',
            DeclarationRequestMapper::body(DeclarationWireSamples::declareChildminder()),
        );
    }

    public function testTheChildminderPreDeclarationOfTheOfficialExample(): void
    {
        WireBodyAssertions::assertBodyIsFixture(
            'predeclare_ama_request_valid',
            DeclarationRequestMapper::body(DeclarationWireSamples::preDeclareChildminder()),
        );
    }

    public function testTheHomeCareDeclarationOfTheOfficialExample(): void
    {
        foreach (['declare_ged_request_valid', 'predeclare_ged_request_valid'] as $fixture) {
            WireBodyAssertions::assertBodyIsFixture($fixture, DeclarationRequestMapper::body(DeclarationWireSamples::homeCare()));
        }
    }

    public function testTheOfficialFixturesDifferFromTheValidOnesOnlyByTheDocumentedReplacements(): void
    {
        // The official sample sends both identifications: it cannot be built from the domain (ADR 0004).
        $official = Fixtures::json('declare_ama_request');
        $valid = Fixtures::json('declare_ama_request_valid');

        static::assertSame(['dtNaissanceSalarie' => '1980-01-30', 'nirSalarie' => 'string', 'numeroPajeSalarie' => 'string'], $official['inputSp']);
        unset($official['inputSp'], $valid['inputSp']);
        static::assertSame($official, $valid);
    }

    public function testTheExactBodyOfTheChildminderExample(): void
    {
        static::assertSame(
            '{"inputDeclCommun":{"dtDebutPeriode":"2020-01-01","dtFinPeriode":"2020-01-01","dtPaiementSalaire":"2020-01-01",'
            . '"mntAcompteSalarie":0.00,"mntIndemnitesKilometriques":0.00,"mntSalaireHoraireNet":0.00,'
            . '"mntSalaireNetMensuel":500.00,"nbHeures":100,"nbHeuresSpecifiques":0,"nbJoursCongesPayes":0},'
            . '"inputDeclEnfant":[{"dtNaissanceEnfant":"2015-01-12","nomEnfant":"L\'HOMME","prenomEnfant":"JEAN-MICHEL",'
            . '"joursAccueilHandicap":10,"joursAccueil24Consecutive":0}],'
            . '"inputSp":{"dtNaissanceSalarie":"1980-01-30","numeroPajeSalarie":"Y2926822480004"},'
            . '"finDeContrat":{"mntIndDepartRetraite":0.00,"mntIndCcp":0.00,"mntIndPreavis":0.00,"mntIndPrecarite":0.00},'
            . '"mntIndemnitesEntretien":0.00,"mntIndemnitesRepas":0.00,"nbHeuresComplementaires":0,"nbHeuresMajorees":0,'
            . '"nbJoursActivite":10}',
            JsonEncoder::encode(DeclarationRequestMapper::body(DeclarationWireSamples::declareChildminder())),
        );
    }

    public function testOptionalValuesThatAreNotSetAreOmittedFromAChildminderBody(): void
    {
        $declaration = ChildminderDeclaration::of(
            DeclarationWireSamples::common(),
            [ChildminderChild::of(DeclarationWireSamples::child(), null, null)],
            EmployeeByNir::of(Nir::of('123456789012345')),
            null,
            31,
            null,
            null,
            null,
            null,
        );

        static::assertSame(
            '{"inputDeclCommun":{"dtDebutPeriode":"2020-01-01","dtFinPeriode":"2020-01-01","dtPaiementSalaire":"2020-01-01",'
            . '"mntAcompteSalarie":0.00,"mntIndemnitesKilometriques":0.00,"mntSalaireHoraireNet":0.00,'
            . '"mntSalaireNetMensuel":500.00,"nbHeures":100,"nbHeuresSpecifiques":0,"nbJoursCongesPayes":0},'
            . '"inputDeclEnfant":[{"dtNaissanceEnfant":"2015-01-12","nomEnfant":"L\'HOMME","prenomEnfant":"JEAN-MICHEL"}],'
            . '"inputSp":{"nirSalarie":"123456789012345"},"nbJoursActivite":31}',
            JsonEncoder::encode(DeclarationRequestMapper::body($declaration)),
        );
    }

    public function testOptionalValuesThatAreNotSetAreOmittedFromAHomeCareBody(): void
    {
        $declaration = HomeCareDeclaration::of(
            CalculationMode::flatRate(),
            DeclarationWireSamples::common(),
            [DeclarationWireSamples::child(), DeclarationWireSamples::child()],
            EmployeeByNir::of(Nir::of('123456789012345')),
            null,
            null,
            null,
            null,
        );

        static::assertSame(
            '{"cdModeCalcul":"F","inputDeclCommun":{"dtDebutPeriode":"2020-01-01","dtFinPeriode":"2020-01-01",'
            . '"dtPaiementSalaire":"2020-01-01","mntAcompteSalarie":0.00,"mntIndemnitesKilometriques":0.00,'
            . '"mntSalaireHoraireNet":0.00,"mntSalaireNetMensuel":500.00,"nbHeures":100,"nbHeuresSpecifiques":0,'
            . '"nbJoursCongesPayes":0},'
            . '"inputDeclEnfant":[{"dtNaissanceEnfant":"2015-01-12","nomEnfant":"L\'HOMME","prenomEnfant":"JEAN-MICHEL"},'
            . '{"dtNaissanceEnfant":"2015-01-12","nomEnfant":"L\'HOMME","prenomEnfant":"JEAN-MICHEL"}],'
            . '"inputSp":{"nirSalarie":"123456789012345"}}',
            JsonEncoder::encode(DeclarationRequestMapper::body($declaration)),
        );
    }

    public function testAnotherDeclarationIsRefused(): void
    {
        $other = new class implements Declaration {
            public function common(): DeclarationCommon
            {
                return DeclarationWireSamples::common();
            }

            public function employee(): DeclaredEmployee
            {
                return DeclarationWireSamples::employee();
            }

            public function children(): array
            {
                return [];
            }

            public function activityType(): DeclarationActivity
            {
                return DeclarationActivity::HomeCare;
            }
        };

        try {
            DeclarationRequestMapper::body($other);
            static::fail('Expected an InvariantViolation.');
        } catch (InvariantViolation $violation) {
            static::assertSame('declaration_request.unsupported', $violation->violationCode());
            static::assertSame(
                'The declaration is neither a childminder nor a home childcare declaration.',
                $violation->getMessage(),
            );
        }
    }
}
