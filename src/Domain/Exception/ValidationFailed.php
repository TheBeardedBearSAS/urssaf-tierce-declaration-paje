<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Validation\ValidationResult;

/**
 * Thrown when a validation result containing errors must stop the current use case.
 */
final class ValidationFailed extends DomainException
{
    private function __construct(private readonly ValidationResult $result)
    {
        $errorCount = \count($result->errors());
        parent::__construct(
            \sprintf('Validation failed with %d error(s).', $errorCount),
            'validation.failed',
            ['error_count' => $errorCount],
        );
    }

    public static function withResult(ValidationResult $result): self
    {
        return new self($result);
    }

    public function result(): ValidationResult
    {
        return $this->result;
    }
}
