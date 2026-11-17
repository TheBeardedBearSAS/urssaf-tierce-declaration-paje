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

use Closure;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvalidValue;

/**
 * Parses raw fields into value objects and accumulates every violation (notification pattern, ADR 0000).
 *
 * A raw string that is null or "" is missing. Factories and validators create one collector per nested object and
 * {@see self::merge()} it under the nested path ("address.postalCode", "hourlyIncome.supplements[1].amount").
 *
 * One entry point per kind of raw field (text, integer, boolean, nested result), hence the method count.
 *
 * @internal
 *
 * @mago-expect lint:too-many-methods
 */
final class ViolationCollector
{
    private ValidationResult $result;

    public function __construct()
    {
        $this->result = ValidationResult::valid();
    }

    /**
     * Reports "<subject>.required" when the value is missing.
     *
     * @template T of object
     *
     * @param Closure(string): T $factory
     *
     * @param-immediately-invoked-callable $factory
     *
     * @return T|null null when missing or invalid
     */
    public function required(string $path, string $subject, ?string $raw, Closure $factory): ?object
    {
        if ($raw === null || $raw === '') {
            $this->addRequired($path, $subject);

            return null;
        }

        return $this->attempt($path, static fn() => $factory($raw));
    }

    /**
     * @template T of object
     *
     * @param Closure(string): T $factory
     *
     * @param-immediately-invoked-callable $factory
     *
     * @return T|null null when missing or invalid
     */
    public function optional(string $path, ?string $raw, Closure $factory): ?object
    {
        return $raw === null || $raw === '' ? null : $this->attempt($path, static fn() => $factory($raw));
    }

    /**
     * Runs the factory and reports its {@see InvalidValue} at the given path.
     *
     * @template T of object
     *
     * @param Closure(): T $factory
     *
     * @param-immediately-invoked-callable $factory
     *
     * @return T|null null when invalid
     */
    public function attempt(string $path, Closure $factory): ?object
    {
        try {
            return $factory();
        } catch (InvalidValue $invalidValue) {
            $this->add($invalidValue->toViolation($path));

            return null;
        }
    }

    /**
     * Reports "<subject>.required" when the value is missing and "<subject>.out_of_range" when it is outside
     * [$min, $max].
     *
     * @return int|null null when missing or out of range
     */
    public function requiredInteger(string $path, string $subject, ?int $raw, int $min, int $max): ?int
    {
        if ($raw === null) {
            $this->addRequired($path, $subject);

            return null;
        }

        return $this->withinRange($path, $subject, $raw, $min, $max);
    }

    /**
     * @return int|null null when missing or out of range
     */
    public function optionalInteger(string $path, string $subject, ?int $raw, int $min, int $max): ?int
    {
        return $raw === null ? null : $this->withinRange($path, $subject, $raw, $min, $max);
    }

    /**
     * Reports "<subject>.required" when the value is missing.
     */
    public function requiredBool(string $path, string $subject, ?bool $raw): ?bool
    {
        if ($raw === null) {
            $this->addRequired($path, $subject);
        }

        return $raw;
    }

    public function add(Violation $violation): void
    {
        $this->result = $this->result->with($violation);
    }

    public function merge(ValidationResult $result, string $pathPrefix): void
    {
        $this->result = $this->result->merge($result->withPathPrefix($pathPrefix));
    }

    public function errorCount(): int
    {
        return \count($this->result->errors());
    }

    public function result(): ValidationResult
    {
        return $this->result;
    }

    private function addRequired(string $path, string $subject): void
    {
        $this->add(Violation::error(
            $path,
            $subject . '.required',
            \sprintf('The %s is required.', str_replace('_', ' ', $subject)),
        ));
    }

    private function withinRange(string $path, string $subject, int $value, int $min, int $max): ?int
    {
        if ($value < $min || $value > $max) {
            $this->add(InvalidValue::outOfRange($subject, $min, $max)->toViolation($path));

            return null;
        }

        return $value;
    }
}
