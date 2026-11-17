<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Child;

/**
 * `VerificationEnfantPe` (022, api-reference §2.3): the searched child (`inputEnfantPe`) and its entitlement status
 * (`reponseStatutEnfant`). An item without `reponseStatutEnfant` is skipped by the response mapper (api-reference §5).
 * The child is nullable because the response schema declares it optional. Personal data:
 * masked in debug output.
 */
final readonly class ChildCheck
{
    private function __construct(
        private ?Child $child,
        private ChildEntitlementStatus $status,
    ) {}

    public static function of(?Child $child, ChildEntitlementStatus $status): self
    {
        return new self($child, $status);
    }

    public function child(): ?Child
    {
        return $this->child;
    }

    public function status(): ChildEntitlementStatus
    {
        return $this->status;
    }

    /**
     * @return array<string, int|string>
     */
    public function __debugInfo(): array
    {
        return ['status' => $this->status->raw(), 'child' => '***'];
    }
}
