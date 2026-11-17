<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Validation;

use LogicException;
use stdClass;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Validation\ValidationResult;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Validation\Validator;

/**
 * Test double: returns a fixed result, provided it receives the expected subject.
 *
 * @implements Validator<stdClass>
 */
final readonly class SubjectCheckingValidator implements Validator
{
    public function __construct(
        private stdClass $expectedSubject,
        private ValidationResult $result,
    ) {}

    public function validate(object $subject): ValidationResult
    {
        if ($subject !== $this->expectedSubject) {
            throw new LogicException('Unexpected subject.');
        }

        return $this->result;
    }
}
