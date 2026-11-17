<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Child\Input;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Child\Child;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Validation\RawFields;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Validation\ValidationResult;

/**
 * Turns a raw list of children (operation 022) into a list of {@see Child}, reporting every violation of every item
 * at once ("children[0].last_name"). No minimum or maximum is enforced: the contract publishes none (api-reference
 * §2.3).
 */
final readonly class ChildListFactory
{
    /**
     * @param array<array-key, mixed> $children list of child maps, see {@see ChildFactory}
     */
    public function validate(#[SensitiveParameter] array $children): ValidationResult
    {
        return RawFields::validate(['children' => $children], $this->parse(...));
    }

    /**
     * @param array<array-key, mixed> $children list of child maps, see {@see ChildFactory}
     *
     * @return list<Child>
     *
     * @throws ValidationFailed with every violation
     */
    public function fromInput(#[SensitiveParameter] array $children): array
    {
        return RawFields::resolve(['children' => $children], $this->parse(...));
    }

    /**
     * @return list<Child>|null null when an item is invalid
     */
    private function parse(RawFields $fields): ?array
    {
        $errorsBefore = $fields->errorCount();
        $children = [];
        // @mago-expect lint:prefer-early-continue A plain filter, kept as such: nothing else happens in the loop.
        foreach ($fields->items('children', (new ChildFactory())->parse(...)) as $child) {
            if ($child instanceof Child) {
                $children[] = $child;
            }
        }

        return $fields->errorCount() > $errorsBefore ? null : $children;
    }
}
