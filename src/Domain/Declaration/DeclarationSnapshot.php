<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeByNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeIdentificationKind;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\PajeEmployeeNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\PajeEmployerNumber;

/**
 * The non-personal part of a declaration kept by the {@see DeclarationRecord} (ADR 0007): the employer number, the
 * activity type, the period, how the employee was identified (kind and, when the host used it, the Pajemploi number) and
 * the fingerprint, which identifies the content for idempotency. No NIR, name, birth date, address nor child data.
 *
 * The public constructor is the factory (more than 5 parts).
 */
final readonly class DeclarationSnapshot
{
    public function __construct(
        private PajeEmployerNumber $employerNumber,
        private DeclarationActivity $activity,
        private DeclarationPeriod $period,
        private EmployeeIdentificationKind $employeeIdentificationKind,
        private ?PajeEmployeeNumber $employeeNumber,
        private DeclarationFingerprint $fingerprint,
    ) {}

    /**
     * Keeps the non-personal part of the declaration sent for the employer; the fingerprint is computed by
     * {@see DeclarationFingerprint::of()}.
     */
    public static function fromDeclaration(PajeEmployerNumber $employerNumber, Declaration $declaration): self
    {
        $employee = $declaration->employee();

        return new self(
            $employerNumber,
            $declaration->activityType(),
            $declaration->common()->period(),
            $employee->kind(),
            $employee instanceof EmployeeByNumber ? $employee->number() : null,
            DeclarationFingerprint::of($declaration),
        );
    }

    public function employerNumber(): PajeEmployerNumber
    {
        return $this->employerNumber;
    }

    public function activity(): DeclarationActivity
    {
        return $this->activity;
    }

    public function period(): DeclarationPeriod
    {
        return $this->period;
    }

    public function employeeIdentificationKind(): EmployeeIdentificationKind
    {
        return $this->employeeIdentificationKind;
    }

    /**
     * Null unless the employee was identified by its Pajemploi number.
     */
    public function employeeNumber(): ?PajeEmployeeNumber
    {
        return $this->employeeNumber;
    }

    public function fingerprint(): DeclarationFingerprint
    {
        return $this->fingerprint;
    }

    public function hasSameContentAs(self $other): bool
    {
        return $this->fingerprint->equals($other->fingerprint);
    }
}
