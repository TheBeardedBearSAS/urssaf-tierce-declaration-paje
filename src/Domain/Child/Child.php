<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Child;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\UpperCaseName;

/**
 * Child `InputEnfant` (022 and the declarations, api-reference §2.3): birth date, last name and first name, all
 * required. Personal data: masked in debug output, no string conversion (ADR 0007).
 */
final readonly class Child
{
    private function __construct(
        #[SensitiveParameter]
        private CalendarDate $birthDate,
        private UpperCaseName $lastName,
        private UpperCaseName $firstName,
    ) {}

    public static function of(#[SensitiveParameter] CalendarDate $birthDate, UpperCaseName $lastName, UpperCaseName $firstName): self
    {
        return new self($birthDate, $lastName, $firstName);
    }

    public function birthDate(): CalendarDate
    {
        return $this->birthDate;
    }

    public function lastName(): UpperCaseName
    {
        return $this->lastName;
    }

    public function firstName(): UpperCaseName
    {
        return $this->firstName;
    }

    public function equals(self $other): bool
    {
        return $this->birthDate->equals($other->birthDate)
            && $this->lastName->equals($other->lastName)
            && $this->firstName->equals($other->firstName);
    }

    /**
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return ['value' => '***'];
    }
}
