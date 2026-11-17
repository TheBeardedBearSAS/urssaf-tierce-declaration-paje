<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Declaration;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\CalculationMode;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\CanonicalForm;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\ChildminderChild;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\ChildminderContractEnd;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\Declaration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationActivity;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationCommon;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationFingerprint;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\HomeCareContractEnd;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\HomeCareDeclaration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\DeclaredEmployee;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeByNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\PajeEmployeeNumber;

/**
 * The fingerprint identifies the content without personal data (ADR 0006, ADR 0007).
 *
 * @mago-expect lint:too-many-methods
 */
final class DeclarationFingerprintTest extends TestCase
{
    private const CHILDMINDER_FORM = <<<'FORM'
        activity=childminder
        start_date=2026-10-01
        end_date=2026-10-31
        payment_date=2026-11-05
        hours=100
        paid_leave_half_days=3
        net_hourly_rate=350
        specific_hours=4
        employee_advance=1000
        net_monthly_salary=50000
        mileage_allowance=1200
        children_count=1
        activity_days=20
        majored_hours=5
        complementary_hours=6
        maintenance_allowance=700
        meal_allowance=800
        child[0]=-,3
        contract_end=-
        FORM;

    private const HOME_CARE_FORM = <<<'FORM'
        activity=home_care
        start_date=2026-10-01
        end_date=2026-10-31
        payment_date=2026-11-05
        hours=100
        paid_leave_half_days=3
        net_hourly_rate=350
        specific_hours=4
        employee_advance=1000
        net_monthly_salary=50000
        mileage_allowance=1200
        children_count=1
        calculation_mode=R
        transport_costs=900
        overtime_hours_25=2
        overtime_hours_50=3
        contract_end=-
        FORM;

    public function testChildminderCanonicalForm(): void
    {
        static::assertSame(self::CHILDMINDER_FORM, CanonicalForm::of(RecordSamples::childminder()));
    }

    public function testHomeCareCanonicalForm(): void
    {
        static::assertSame(self::HOME_CARE_FORM, CanonicalForm::of(RecordSamples::homeCare()));
    }

    public function testFingerprintIsTheSha256OfTheCanonicalForm(): void
    {
        $fingerprint = DeclarationFingerprint::of(RecordSamples::childminder());

        static::assertSame(hash('sha256', self::CHILDMINDER_FORM), $fingerprint->value());
        static::assertSame($fingerprint->value(), CanonicalForm::fingerprint(RecordSamples::childminder()));
        static::assertTrue($fingerprint->equals(DeclarationFingerprint::fromString($fingerprint->value())));
    }

    public function testContractEndsAreInTheCanonicalForm(): void
    {
        $amount = RecordSamples::amount(...);
        $childminderEnd = ChildminderContractEnd::of($amount('1'), $amount('2'), $amount('3'), $amount('4'), $amount('5'), [
            ChildminderChild::of(RecordSamples::childminder()->children()[0]->child(), 6, null),
            ChildminderChild::of(RecordSamples::childminder()->children()[0]->child(), null, 7),
        ]);
        $homeCareEnd = HomeCareContractEnd::of($amount('1'), null, $amount('3'), $amount('4'), $amount('5'));

        static::assertSame(
            str_replace(
                'contract_end=-',
                "retirement_indemnity=100\npaid_leave_compensation=200\nnotice_indemnity=300\nprecarity_indemnity=400\n"
                . "termination_indemnity=500\ncontract_end_child[0]=6,-\ncontract_end_child[1]=-,7",
                self::CHILDMINDER_FORM,
            ),
            CanonicalForm::of(RecordSamples::childminder(null, null, $childminderEnd)),
        );
        static::assertSame(
            str_replace(
                'contract_end=-',
                "retirement_indemnity=100\npaid_leave_compensation=-\nnotice_indemnity=300\nprecarity_indemnity=400\n"
                . 'dismissal_indemnity=500',
                self::HOME_CARE_FORM,
            ),
            CanonicalForm::of(RecordSamples::homeCare(null, null, null, $homeCareEnd)),
        );
    }

    public function testEveryChildIsInTheCanonicalForm(): void
    {
        $second = ChildminderChild::of(RecordSamples::childminder()->children()[0]->child(), 8, null);

        static::assertSame(
            str_replace(
                ['children_count=1', "child[0]=-,3\n"],
                ['children_count=2', "child[0]=-,3\nchild[1]=8,-\n"],
                self::CHILDMINDER_FORM,
            ),
            CanonicalForm::of(RecordSamples::childminder(null, null, null, null, 'JEAN', $second)),
        );
    }

    public function testAbsentOptionalValuesAreWrittenAsDash(): void
    {
        $declaration = RecordSamples::sparseHomeCare();

        static::assertStringContainsString(
            "hours=1\npaid_leave_half_days=-\nnet_hourly_rate=-\nspecific_hours=-\nemployee_advance=-\nnet_monthly_salary=100\n"
            . "mileage_allowance=-\nchildren_count=1\ncalculation_mode=F\ntransport_costs=-\novertime_hours_25=-\novertime_hours_50=-\ncontract_end=-",
            CanonicalForm::of($declaration),
        );
    }

    public function testPersonalDataDoesNotChangeTheFingerprint(): void
    {
        $reference = DeclarationFingerprint::of(RecordSamples::childminder());
        $sameContent = [
            RecordSamples::childminder(RecordSamples::nirEmployee()),
            RecordSamples::childminder(EmployeeByNumber::of(PajeEmployeeNumber::of('Y0000000000001'), RecordSamples::date('1980-01-01'))),
            RecordSamples::childminder(null, null, null, null, 'MARIE'),
        ];

        foreach ($sameContent as $declaration) {
            static::assertTrue($reference->equals(DeclarationFingerprint::of($declaration)));
        }
        static::assertTrue(
            DeclarationFingerprint::of(RecordSamples::homeCare())->equals(DeclarationFingerprint::of(RecordSamples::homeCare(RecordSamples::nirEmployee()))),
        );
    }

    public function testEveryBusinessFieldChangesTheFingerprint(): void
    {
        $base = DeclarationFingerprint::of(RecordSamples::childminder());
        $variants = [
            'hours' => RecordSamples::childminder(null, RecordSamples::common(101)),
            'specific hours' => RecordSamples::childminder(null, RecordSamples::common(100, 5)),
            'no specific hours' => RecordSamples::childminder(null, RecordSamples::common(100, null)),
            'start' => RecordSamples::childminder(null, RecordSamples::common(100, 4, RecordSamples::period('2026-10-02', '2026-10-31', '2026-11-05'))),
            'end' => RecordSamples::childminder(null, RecordSamples::common(100, 4, RecordSamples::period('2026-10-01', '2026-10-30', '2026-11-05'))),
            'payment' => RecordSamples::childminder(null, RecordSamples::common(100, 4, RecordSamples::period('2026-10-01', '2026-10-31', '2026-11-06'))),
            'disabled days' => RecordSamples::childminder(null, null, null, 2),
            'home care' => RecordSamples::homeCare(),
        ];

        $seen = [$base->value()];
        foreach ($variants as $label => $declaration) {
            $value = DeclarationFingerprint::of($declaration)->value();
            static::assertNotContains($value, $seen, $label);
            $seen[] = $value;
        }
    }

    public function testEveryHomeCareFieldChangesTheFingerprint(): void
    {
        $base = CanonicalForm::of(RecordSamples::homeCare());

        static::assertNotSame($base, CanonicalForm::of(RecordSamples::homeCare(null, null, CalculationMode::specific())));
        static::assertNotSame($base, CanonicalForm::of(RecordSamples::homeCare(null, null, null, null, 9)));
    }

    public function testAnotherImplementationOfDeclarationOnlyHasTheCommonLines(): void
    {
        $inner = RecordSamples::homeCare();
        $other = new class ($inner) implements Declaration {
            public function __construct(private readonly HomeCareDeclaration $inner) {}

            public function common(): DeclarationCommon
            {
                return $this->inner->common();
            }

            public function employee(): DeclaredEmployee
            {
                return $this->inner->employee();
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

        static::assertStringEndsWith("mileage_allowance=1200\nchildren_count=0", CanonicalForm::of($other));
    }

    public function testFromStringAcceptsOnlyLowercaseSha256Hexadecimal(): void
    {
        $valid = str_repeat('ab12', 16);

        static::assertSame($valid, DeclarationFingerprint::fromString($valid)->value());
        foreach ([strtoupper($valid), $valid . 'a', substr($valid, 1), $valid . "\n", str_repeat('g', 64), ''] as $invalid) {
            RecordSamples::assertViolation(
                'declaration_fingerprint.invalid_format',
                static fn(): DeclarationFingerprint => DeclarationFingerprint::fromString($invalid),
            );
        }
        static::assertFalse(DeclarationFingerprint::fromString($valid)->equals(DeclarationFingerprint::fromString(str_repeat('cd34', 16))));
    }
}
