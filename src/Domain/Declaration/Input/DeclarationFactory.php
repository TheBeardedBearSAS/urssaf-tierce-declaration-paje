<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\Input;

use Closure;
use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Child\Child;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Child\Input\ChildFactory;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\CalculationMode;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\ChildminderChild;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\ChildminderDeclaration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\Declaration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationActivity;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\HomeCareDeclaration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\Input\DeclaredEmployeeFactory;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Amount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Validation\RawFields;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Validation\ValidationResult;

/**
 * Turns the raw body of a salary declaration or pre-declaration into a {@see Declaration}, reporting every violation
 * at once with nested paths ("common.hours", "employee.nir", "children[0].last_name", "contract_end.notice_indemnity",
 * notification pattern, ADR 0000 item 4). The rules are those of the aggregates; nothing is added here.
 *
 * The aggregates are built even when a part is missing or invalid, with stand-ins ({@see DeclarationPlaceholders}),
 * so that a missing salary does not hide an out-of-range hours count. The declaration is returned only without any
 * violation.
 *
 * Input keys of a childminder declaration (`InputDeclarationAma`): "common" ({@see DeclarationCommonFactory}),
 * "employee" ({@see DeclaredEmployeeFactory}), "children" (list, {@see ChildminderChildFactory}), "contract_end"
 * ({@see ChildminderContractEndFactory}, optional), "activity_days" (integer, required), "majored_hours",
 * "complementary_hours" (integers), "maintenance_allowance", "meal_allowance" (decimal strings).
 *
 * Input keys of a home care declaration (`InputDeclarationGed`): "calculation_mode" (required), "common", "employee",
 * "children" (list, {@see ChildFactory}), "contract_end" ({@see HomeCareContractEndFactory}, optional),
 * "transport_costs" (decimal string), "overtime_hours_25", "overtime_hours_50" (integers).
 */
final readonly class DeclarationFactory
{
    /**
     * @param array<array-key, mixed> $input
     */
    public function validate(DeclarationActivity $activity, #[SensitiveParameter] array $input): ValidationResult
    {
        return RawFields::validate($input, match ($activity) {
            DeclarationActivity::Childminder => $this->parseChildminder(...),
            DeclarationActivity::HomeCare => $this->parseHomeCare(...),
        });
    }

    /**
     * @param array<array-key, mixed> $input
     *
     * @throws ValidationFailed with every violation
     */
    public function fromInput(DeclarationActivity $activity, #[SensitiveParameter] array $input): Declaration
    {
        return match ($activity) {
            DeclarationActivity::Childminder => $this->childminder($input),
            DeclarationActivity::HomeCare => $this->homeCare($input),
        };
    }

    /**
     * @param array<array-key, mixed> $input
     *
     * @throws ValidationFailed with every violation
     */
    public function childminder(#[SensitiveParameter] array $input): ChildminderDeclaration
    {
        return RawFields::resolve($input, $this->parseChildminder(...));
    }

    /**
     * @param array<array-key, mixed> $input
     *
     * @throws ValidationFailed with every violation
     */
    public function homeCare(#[SensitiveParameter] array $input): HomeCareDeclaration
    {
        return RawFields::resolve($input, $this->parseHomeCare(...));
    }

    private function parseChildminder(RawFields $fields): ?ChildminderDeclaration
    {
        $errorsBefore = $fields->errorCount();
        $common = $fields->nested('common', (new DeclarationCommonFactory())->parse(...));
        $employee = $fields->nested('employee', (new DeclaredEmployeeFactory())->parse(...));
        $children = $fields->items('children', (new ChildminderChildFactory())->parse(...));
        $contractEnd = $fields->optionalNested('contract_end', (new ChildminderContractEndFactory())->parse(...));
        $activityDays = $fields->requiredInteger('activity_days');
        $majoredHours = $fields->integer('majored_hours');
        $complementaryHours = $fields->integer('complementary_hours');
        $maintenanceAllowance = $fields->optional('maintenance_allowance', Amount::fromDecimalString(...));
        $mealAllowance = $fields->optional('meal_allowance', Amount::fromDecimalString(...));
        $declaration = $fields->build('', static fn(): ChildminderDeclaration => ChildminderDeclaration::of(
            $common ?? DeclarationPlaceholders::common(),
            self::present($children, DeclarationPlaceholders::childminderChild(...)),
            $employee ?? DeclarationPlaceholders::employee(),
            $contractEnd,
            $activityDays ?? DeclarationPlaceholders::ACTIVITY_DAYS,
            $majoredHours,
            $complementaryHours,
            $maintenanceAllowance,
            $mealAllowance,
        ));

        return $fields->errorCount() > $errorsBefore ? null : $declaration;
    }

    private function parseHomeCare(RawFields $fields): ?HomeCareDeclaration
    {
        $errorsBefore = $fields->errorCount();
        $calculationMode = $fields->required('calculation_mode', CalculationMode::of(...));
        $common = $fields->nested('common', (new DeclarationCommonFactory())->parse(...));
        $employee = $fields->nested('employee', (new DeclaredEmployeeFactory())->parse(...));
        $children = $fields->items('children', (new ChildFactory())->parse(...));
        $contractEnd = $fields->optionalNested('contract_end', (new HomeCareContractEndFactory())->parse(...));
        $transportCosts = $fields->optional('transport_costs', Amount::fromDecimalString(...));
        $overtimeHours25 = $fields->integer('overtime_hours_25');
        $overtimeHours50 = $fields->integer('overtime_hours_50');
        $declaration = $fields->build('', static fn(): HomeCareDeclaration => HomeCareDeclaration::of(
            $calculationMode ?? CalculationMode::real(),
            $common ?? DeclarationPlaceholders::common(),
            self::present($children, DeclarationPlaceholders::child(...)),
            $employee ?? DeclarationPlaceholders::employee(),
            $contractEnd,
            $transportCosts,
            $overtimeHours25,
            $overtimeHours50,
        ));

        return $fields->errorCount() > $errorsBefore ? null : $declaration;
    }

    /**
     * The children that were built, or a single stand-in when every given child is invalid (so that the aggregate does
     * not report an empty list on top of the violations of the items). An empty input stays empty.
     *
     * @template T of Child|ChildminderChild
     *
     * @param list<T|null> $items
     * @param Closure(): T $placeholder
     *
     * @return list<T>
     */
    private static function present(array $items, Closure $placeholder): array
    {
        $built = [];
        // @mago-expect lint:prefer-early-continue A plain filter, kept as such: nothing else happens in the loop.
        foreach ($items as $item) {
            if ($item !== null) {
                $built[] = $item;
            }
        }

        return $built === [] && $items !== [] ? [$placeholder()] : $built;
    }
}
