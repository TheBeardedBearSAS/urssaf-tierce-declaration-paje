<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\OnlineAccountState;

/**
 * Result of operation 021 `OutputVerificationSp` (also nested in 023, api-reference §2.2): the Pajemploi number found
 * (raw, the official example returns the placeholder "string"), the childminder / home care capabilities
 * (`indcAma`, `indcGed`) and the online account state. Personal data (employee number): masked in debug output.
 */
final readonly class EmployeeAccount
{
    private function __construct(
        private ?string $employeeNumber,
        private bool $childminder,
        private bool $homeCare,
        private OnlineAccountState $state,
    ) {}

    public static function of(
        #[SensitiveParameter]
        ?string $employeeNumber,
        bool $childminder,
        bool $homeCare,
        OnlineAccountState $state,
    ): self {
        return new self($employeeNumber, $childminder, $homeCare, $state);
    }

    public function employeeNumber(): ?string
    {
        return $this->employeeNumber;
    }

    /**
     * `indcAma`: the employee may work as a childminder.
     */
    public function childminder(): bool
    {
        return $this->childminder;
    }

    /**
     * `indcGed`: the employee may work as a home carer.
     */
    public function homeCare(): bool
    {
        return $this->homeCare;
    }

    public function state(): OnlineAccountState
    {
        return $this->state;
    }

    /**
     * @return array<string, bool|string>
     */
    public function __debugInfo(): array
    {
        return [
            'employeeNumber' => '***',
            'childminder' => $this->childminder,
            'homeCare' => $this->homeCare,
            'state' => $this->state->raw(),
        ];
    }
}
