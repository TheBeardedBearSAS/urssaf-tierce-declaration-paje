<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Child;

/**
 * `reponseStatutEnfant` of a checked child (022): the raw integer is always kept, {@see self::documented()} is null
 * for a value the specification does not list.
 */
final readonly class ChildEntitlementStatus
{
    private function __construct(private int $raw) {}

    public static function of(int $raw): self
    {
        return new self($raw);
    }

    public function raw(): int
    {
        return $this->raw;
    }

    public function documented(): ?ChildEntitlement
    {
        return ChildEntitlement::tryFrom($this->raw);
    }

    public function equals(self $other): bool
    {
        return $this->raw === $other->raw;
    }
}
