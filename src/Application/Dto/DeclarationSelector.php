<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\ExternalReference;

/**
 * Designates a declaration record of a tenant: by record id or by the host external reference (PAJE has no URSSAF
 * declaration id, ADR 0006).
 */
final readonly class DeclarationSelector
{
    private function __construct(public DeclarationRecordId|ExternalReference $reference) {}

    public static function byRecordId(DeclarationRecordId $recordId): self
    {
        return new self($recordId);
    }

    public static function byExternalReference(ExternalReference $externalReference): self
    {
        return new self($externalReference);
    }
}
