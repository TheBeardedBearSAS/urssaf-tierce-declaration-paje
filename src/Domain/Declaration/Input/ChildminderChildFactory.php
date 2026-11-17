<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\Input;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Child\Input\ChildFactory;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\ChildminderChild;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Validation\RawFields;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Validation\ValidationResult;

/**
 * Turns the raw child of a childminder declaration (`InputEnfantAma`) into a {@see ChildminderChild}, reporting every
 * violation (notification pattern, ADR 0000 item 4).
 *
 * Input keys: those of {@see ChildFactory} plus "disabled_child_care_days" and "consecutive_24h_days" (integers).
 */
final readonly class ChildminderChildFactory
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
    public function fromInput(#[SensitiveParameter] array $input): ChildminderChild
    {
        return RawFields::resolve($input, $this->parse(...));
    }

    /**
     * @internal
     */
    public function parse(RawFields $fields): ?ChildminderChild
    {
        $errorsBefore = $fields->errorCount();
        $child = (new ChildFactory())->parse($fields);
        $disabledChildCareDays = $fields->integer('disabled_child_care_days');
        $consecutive24hDays = $fields->integer('consecutive_24h_days');
        $childminderChild = $fields->build('', static fn(): ChildminderChild => ChildminderChild::of(
            $child ?? DeclarationPlaceholders::child(),
            $disabledChildCareDays,
            $consecutive24hDays,
        ));

        return $fields->errorCount() > $errorsBefore ? null : $childminderChild;
    }
}
