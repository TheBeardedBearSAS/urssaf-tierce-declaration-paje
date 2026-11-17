<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\CalendarDate;

/**
 * Employee identified by its NIR (priority 2), with the optional birth date of the query (`dtNaissanceSalarie`).
 * Personal data: masked in debug output.
 */
final readonly class EmployeeByNir implements DeclaredEmployee
{
    private function __construct(
        private Nir $nir,
        #[SensitiveParameter]
        private ?CalendarDate $birthDate,
    ) {}

    public static function of(Nir $nir, #[SensitiveParameter] ?CalendarDate $birthDate = null): self
    {
        return new self($nir, $birthDate);
    }

    public function kind(): EmployeeIdentificationKind
    {
        return EmployeeIdentificationKind::Nir;
    }

    public function nir(): Nir
    {
        return $this->nir;
    }

    public function birthDate(): ?CalendarDate
    {
        return $this->birthDate;
    }

    /**
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return ['value' => '***'];
    }
}
