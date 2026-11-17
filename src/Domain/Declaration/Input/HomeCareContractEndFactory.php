<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\Input;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\HomeCareContractEnd;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Amount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Validation\RawFields;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Validation\ValidationResult;

/**
 * Turns the raw `finDeContrat` of a home care declaration into a {@see HomeCareContractEnd}, reporting every
 * violation (notification pattern, ADR 0000 item 4). The home care `finDeContrat` has no child list (api-reference
 * §2.8).
 *
 * Input keys (decimal strings, all optional): "retirement_indemnity", "paid_leave_compensation", "notice_indemnity",
 * "precarity_indemnity", "dismissal_indemnity".
 */
final readonly class HomeCareContractEndFactory
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
    public function fromInput(#[SensitiveParameter] array $input): HomeCareContractEnd
    {
        return RawFields::resolve($input, $this->parse(...));
    }

    /**
     * @internal
     */
    public function parse(RawFields $fields): ?HomeCareContractEnd
    {
        $errorsBefore = $fields->errorCount();
        $retirement = $fields->optional('retirement_indemnity', Amount::fromDecimalString(...));
        $paidLeave = $fields->optional('paid_leave_compensation', Amount::fromDecimalString(...));
        $notice = $fields->optional('notice_indemnity', Amount::fromDecimalString(...));
        $precarity = $fields->optional('precarity_indemnity', Amount::fromDecimalString(...));
        $dismissal = $fields->optional('dismissal_indemnity', Amount::fromDecimalString(...));
        $contractEnd = $fields->build('', static fn(): HomeCareContractEnd => HomeCareContractEnd::of(
            $retirement,
            $paidLeave,
            $notice,
            $precarity,
            $dismissal,
        ));

        return $fields->errorCount() > $errorsBefore ? null : $contractEnd;
    }
}
