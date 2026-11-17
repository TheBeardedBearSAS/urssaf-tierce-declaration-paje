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

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\ChildminderContractEnd;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Amount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Validation\RawFields;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Validation\ValidationResult;

/**
 * Turns the raw `finDeContrat` of a childminder declaration into a {@see ChildminderContractEnd}, reporting every
 * violation (notification pattern, ADR 0000 item 4). The deprecated `finDeContrat.enfants` is not accepted (api-reference
 * §2.7).
 *
 * Input keys (decimal strings, all optional): "retirement_indemnity", "paid_leave_compensation", "notice_indemnity",
 * "precarity_indemnity", "termination_indemnity".
 */
final readonly class ChildminderContractEndFactory
{
    /**
     * @param array<array-key, mixed> $input
     */
    public function validate(#[SensitiveParameter] array $input): ValidationResult
    {
        return RawFields::validate($input, $this->parse(...));
    }

    /**
     * @param array<array-key, mixed> $input
     *
     * @throws ValidationFailed with every violation
     */
    public function fromInput(#[SensitiveParameter] array $input): ChildminderContractEnd
    {
        return RawFields::resolve($input, $this->parse(...));
    }

    /**
     * @internal
     */
    public function parse(RawFields $fields): ?ChildminderContractEnd
    {
        $errorsBefore = $fields->errorCount();
        $retirement = $fields->optional('retirement_indemnity', Amount::fromDecimalString(...));
        $paidLeave = $fields->optional('paid_leave_compensation', Amount::fromDecimalString(...));
        $notice = $fields->optional('notice_indemnity', Amount::fromDecimalString(...));
        $precarity = $fields->optional('precarity_indemnity', Amount::fromDecimalString(...));
        $termination = $fields->optional('termination_indemnity', Amount::fromDecimalString(...));
        $contractEnd = $fields->build('', static fn(): ChildminderContractEnd => ChildminderContractEnd::of(
            $retirement,
            $paidLeave,
            $notice,
            $precarity,
            $termination,
        ));

        return $fields->errorCount() > $errorsBefore ? null : $contractEnd;
    }
}
