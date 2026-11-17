<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeAccount;

/**
 * Read model of an employee account (method 021, 023). The employee number is personal data (ADR 0007): masked in debug
 * output.
 */
final readonly class EmployeeAccountView
{
    private function __construct(
        #[SensitiveParameter]
        public ?string $employeeNumber,
        public bool $childminder,
        public bool $homeCare,
        public string $onlineAccountState,
    ) {}

    public static function fromAccount(EmployeeAccount $account): self
    {
        return new self($account->employeeNumber(), $account->childminder(), $account->homeCare(), $account->state()->raw());
    }

    /**
     * @return array<string, bool|string|null>
     */
    public function __debugInfo(): array
    {
        return [
            'employeeNumber' => $this->employeeNumber === null ? null : '***',
            'childminder' => $this->childminder,
            'homeCare' => $this->homeCare,
            'onlineAccountState' => $this->onlineAccountState,
        ];
    }
}
