<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception;

/**
 * Thrown when a record looked up by identifier does not exist.
 *
 * The identifier ends up in the message and the params: it must be a technical id (external reference, record id),
 * never personal data such as a Pajemploi number or a NIR (ADR 0007).
 *
 * Violation code: "<record_type>.not_found" (e.g. "employer_account.not_found").
 */
final class RecordNotFound extends DomainException
{
    public static function withIdentifier(string $recordType, string $identifier): self
    {
        return new self(
            \sprintf('No %s found for identifier "%s".', $recordType, $identifier),
            $recordType . '.not_found',
            ['record_type' => $recordType, 'identifier' => $identifier],
        );
    }

    public function recordType(): string
    {
        return (string) $this->params()['record_type'];
    }

    public function identifier(): string
    {
        return (string) $this->params()['identifier'];
    }
}
