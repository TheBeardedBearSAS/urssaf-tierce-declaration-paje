<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Validation;

/**
 * Composite: runs every validator and merges their results in order.
 *
 * @template T of object
 *
 * @implements Validator<T>
 */
final readonly class AllOf implements Validator
{
    /**
     * @param array<Validator<T>> $validators
     */
    private function __construct(private array $validators) {}

    /**
     * @template U of object
     *
     * @param Validator<U> ...$validators
     *
     * @return self<U>
     */
    public static function of(Validator ...$validators): self
    {
        return new self($validators);
    }

    public function validate(object $subject): ValidationResult
    {
        return array_reduce(
            $this->validators,
            static fn(ValidationResult $result, Validator $validator): ValidationResult => $result->merge(
                $validator->validate($subject),
            ),
            ValidationResult::valid(),
        );
    }
}
