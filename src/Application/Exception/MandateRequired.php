<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Exception;

use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\DomainException;

/**
 * Thrown when a declaration operation (045, 046, 050, 051) is refused locally because no non-cancelled local mandate is
 * known for the employer (ADR 0009, opt-in guard). Nothing was sent to URSSAF. The employer number never appears in the
 * message or the parameters (ADR 0007).
 *
 * Violation code: "mandate.required".
 */
final class MandateRequired extends DomainException
{
    public static function forOperation(Operation $operation): self
    {
        return new self(
            \sprintf('No active local mandate is known for the employer: method %s is refused locally (ADR 0009).', $operation->value),
            'mandate.required',
            ['operation' => $operation->value],
        );
    }
}
