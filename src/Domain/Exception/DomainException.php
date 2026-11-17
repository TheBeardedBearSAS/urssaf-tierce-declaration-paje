<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Validation\Violation;
use Throwable;

/**
 * Base class of every domain exception. Each one carries a stable snake_case violation code
 * (e.g. "amount.negative") and scalar parameters, and can be converted into a validation violation.
 *
 * Messages are English and never contain secrets nor the rejected raw value.
 */
abstract class DomainException extends \DomainException implements UrssafException
{
    /**
     * @param array<string, scalar> $params
     */
    protected function __construct(
        string $message,
        private readonly string $violationCode,
        private readonly array $params,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    final public function violationCode(): string
    {
        return $this->violationCode;
    }

    /**
     * @return array<string, scalar>
     */
    final public function params(): array
    {
        return $this->params;
    }

    /**
     * Converts the exception into an error violation located at the given path (empty path = root).
     */
    final public function toViolation(string $path): Violation
    {
        return Violation::error($path, $this->violationCode, $this->getMessage(), $this->params);
    }
}
