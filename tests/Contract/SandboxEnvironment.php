<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Contract;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Credentials;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Child\Child;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\PajeEmployeeNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\PajeEmployerNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\UpperCaseName;

/**
 * The URSSAF_SANDBOX_* variables, read once and validated lazily: a missing (or blank) value never throws, a configured
 * but wrong value throws a SandboxMisconfigured naming the variable only. The employer pair and the child triple are
 * all-or-nothing.
 *
 * One accessor per variable, hence the method count and the complexity.
 *
 * @mago-expect lint:too-many-methods
 * @mago-expect lint:cyclomatic-complexity
 */
final readonly class SandboxEnvironment
{
    public const CLIENT_ID = 'URSSAF_SANDBOX_CLIENT_ID';
    // @mago-expect lint:no-literal-password The constant is the name of the environment variable, not a secret.
    public const CLIENT_SECRET = 'URSSAF_SANDBOX_CLIENT_SECRET';
    public const BASE_URI = 'URSSAF_SANDBOX_BASE_URI';
    public const TOKEN_URI = 'URSSAF_SANDBOX_TOKEN_URI';
    public const EMPLOYER_NUMBER = 'URSSAF_SANDBOX_EMPLOYER_NUMBER';
    public const EMPLOYER_BIRTH_DATE = 'URSSAF_SANDBOX_EMPLOYER_BIRTH_DATE';
    public const EMPLOYEE_NUMBER = 'URSSAF_SANDBOX_EMPLOYEE_NUMBER';
    public const CHILD_LAST_NAME = 'URSSAF_SANDBOX_CHILD_LAST_NAME';
    public const CHILD_FIRST_NAME = 'URSSAF_SANDBOX_CHILD_FIRST_NAME';
    public const CHILD_BIRTH_DATE = 'URSSAF_SANDBOX_CHILD_BIRTH_DATE';
    public const ALLOW_WRITES = 'URSSAF_SANDBOX_ALLOW_WRITES';
    public const PRODUCTION_HOST = 'api.urssaf.fr';

    private const ALL = [
        self::CLIENT_ID,
        self::CLIENT_SECRET,
        self::BASE_URI,
        self::TOKEN_URI,
        self::EMPLOYER_NUMBER,
        self::EMPLOYER_BIRTH_DATE,
        self::EMPLOYEE_NUMBER,
        self::CHILD_LAST_NAME,
        self::CHILD_FIRST_NAME,
        self::CHILD_BIRTH_DATE,
        self::ALLOW_WRITES,
    ];

    /**
     * @param array<string, string> $values the raw variables, by name
     */
    private function __construct(
        #[SensitiveParameter]
        private array $values,
    ) {}

    public static function fromProcess(): self
    {
        $values = [];
        foreach (self::ALL as $name) {
            $value = self::env($name);
            if (null !== $value) {
                $values[$name] = $value;
            }
        }

        return new self($values);
    }

    /**
     * @param array<string, string> $values
     */
    public static function fromArray(#[SensitiveParameter] array $values): self
    {
        return new self($values);
    }

    /**
     * Trimmed value of a process variable, null when unset or blank (CI passes an empty string for an unset variable).
     */
    public static function env(string $name): ?string
    {
        $value = getenv($name);

        return \is_string($value) && '' !== trim($value) ? trim($value) : null;
    }

    public function hasCredentials(): bool
    {
        return \array_key_exists(self::CLIENT_ID, $this->values) && \array_key_exists(self::CLIENT_SECRET, $this->values);
    }

    public function credentials(): Credentials
    {
        return Credentials::of($this->values[self::CLIENT_ID] ?? '', $this->values[self::CLIENT_SECRET] ?? '');
    }

    /**
     * @throws SandboxMisconfigured when the host is the production host
     */
    public function baseUri(): ?string
    {
        return $this->safeUri(self::BASE_URI);
    }

    /**
     * @throws SandboxMisconfigured
     */
    public function tokenUri(): ?string
    {
        return $this->safeUri(self::TOKEN_URI);
    }

    /**
     * An absolute https URI with a host, which is not the production host (compared lowercased, without trailing dot).
     *
     * @throws SandboxMisconfigured
     */
    private function safeUri(string $variable): ?string
    {
        $uri = $this->values[$variable] ?? null;
        if (null === $uri) {
            return null;
        }

        $scheme = parse_url($uri, \PHP_URL_SCHEME);
        $host = parse_url($uri, \PHP_URL_HOST);
        if (!\is_string($scheme) || 'https' !== strtolower($scheme)) {
            throw SandboxMisconfigured::variable($variable, 'must be an https URI', null);
        }
        if (!\is_string($host)) {
            throw SandboxMisconfigured::variable($variable, 'has no host', null);
        }
        if (self::PRODUCTION_HOST === rtrim(strtolower($host), '.')) {
            throw SandboxMisconfigured::variable($variable, 'targets the production host, which is refused', null);
        }

        return $uri;
    }

    /**
     * @throws SandboxMisconfigured
     */
    public function employer(): ?EmployerReference
    {
        $number = $this->values[self::EMPLOYER_NUMBER] ?? null;
        $birthDate = $this->values[self::EMPLOYER_BIRTH_DATE] ?? null;
        if (null === $number && null === $birthDate) {
            return null;
        }
        if (null === $number) {
            throw SandboxMisconfigured::variable(self::EMPLOYER_NUMBER, 'is required with ' . self::EMPLOYER_BIRTH_DATE, null);
        }
        if (null === $birthDate) {
            throw SandboxMisconfigured::variable(self::EMPLOYER_BIRTH_DATE, 'is required with ' . self::EMPLOYER_NUMBER, null);
        }

        try {
            $employerNumber = PajeEmployerNumber::of($number);
        } catch (InvalidValue $e) {
            throw SandboxMisconfigured::variable(self::EMPLOYER_NUMBER, 'is not a 14-character Pajemploi number', $e);
        }

        return EmployerReference::of($employerNumber, $this->date(self::EMPLOYER_BIRTH_DATE, $birthDate));
    }

    /**
     * @throws SandboxMisconfigured
     */
    public function employeeNumber(): ?PajeEmployeeNumber
    {
        $value = $this->values[self::EMPLOYEE_NUMBER] ?? null;
        if (null === $value) {
            return null;
        }

        try {
            return PajeEmployeeNumber::of($value);
        } catch (InvalidValue $e) {
            throw SandboxMisconfigured::variable(self::EMPLOYEE_NUMBER, 'is not a 14-character Pajemploi number', $e);
        }
    }

    /**
     * @throws SandboxMisconfigured
     */
    public function child(): ?Child
    {
        $names = [self::CHILD_LAST_NAME, self::CHILD_FIRST_NAME, self::CHILD_BIRTH_DATE];
        $present = array_values(array_filter($names, fn(string $name): bool => \array_key_exists($name, $this->values)));
        if ([] === $present) {
            return null;
        }
        foreach ($names as $name) {
            if (!\in_array($name, $present, true)) {
                throw SandboxMisconfigured::variable($name, 'is required when another child variable is set', null);
            }
        }

        try {
            return Child::of(
                $this->date(self::CHILD_BIRTH_DATE, $this->values[self::CHILD_BIRTH_DATE] ?? ''),
                UpperCaseName::of($this->values[self::CHILD_LAST_NAME] ?? '', 'child_last_name'),
                UpperCaseName::of($this->values[self::CHILD_FIRST_NAME] ?? '', 'child_first_name'),
            );
        } catch (InvalidValue $e) {
            throw SandboxMisconfigured::variable(
                'URSSAF_SANDBOX_CHILD_*',
                'holds a name that is not upper case without accents (30 characters at most)',
                $e,
            );
        }
    }

    public function writesAllowed(): bool
    {
        return '1' === ($this->values[self::ALLOW_WRITES] ?? null);
    }

    /**
     * @throws SandboxMisconfigured
     */
    private function date(string $variable, #[SensitiveParameter] string $value): CalendarDate
    {
        try {
            return CalendarDate::fromString($value);
        } catch (InvalidValue $e) {
            throw SandboxMisconfigured::variable($variable, 'is not a YYYY-MM-DD date', $e);
        }
    }
}
