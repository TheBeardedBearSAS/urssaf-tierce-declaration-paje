<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\Event\EmployerForgotten;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\Event\EmployerRegistered;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\Event\EmployerStageChanged;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\Event\EmployerStatusRefreshed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\DomainEvent;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Instant;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;

/**
 * Aggregate root: local follow-up of an employer account, per tenant and {@see EmployerReference} (ADR 0006, 0010).
 * Created with {@see self::track()} (by a use case of this package) or {@see self::import()} (existing Pajemploi
 * employer).
 *
 * The identity (id, tenant, external reference, employer, origin, creation instant) is immutable; the mutable part is the
 * {@see EmployerTracking}. The employer number and birth date are stored (ADR 0007) but never leave the aggregate through
 * its events (ADR 0012). Stages follow methods 030 and 031 and never block a call: there is no invalid transition.
 * Every notable change records a domain event, see {@see self::releaseEvents()}.
 *
 * Not final only because Doctrine generates lazy-loading proxies by extending the mapped entities: final by
 * convention, never extend it. For the same reason the identifier is neither readonly nor private: a proxy is created
 * with its identifier, which Doctrine assigns again (another instance of the same value) when the proxy is loaded. It is
 * never reassigned otherwise.
 *
 * The root exposes its identity, transitions and queries, hence the method count.
 *
 * @final
 *
 * @mago-expect lint:too-many-methods
 */
class EmployerAccount
{
    /** @var list<DomainEvent> */
    private array $events = [];

    /**
     * Persistence concurrency token (optimistic lock of the Doctrine bridge): read and written by the ORM through
     * reflection, never by the domain. It is not a domain concept and has no accessor.
     */
    // @mago-expect analysis:unused-property Doctrine optimistic-lock token, read and written by the ORM through reflection.
    private int $version = 1;

    private function __construct(
        protected EmployerAccountId $id,
        private readonly TenantId $tenantId,
        private readonly ExternalReference $externalReference,
        private readonly EmployerReference $employer,
        private readonly EmployerOrigin $origin,
        private readonly Instant $createdAt,
        private EmployerTracking $tracking,
    ) {}

    /**
     * Employer tracked by a use case of this package. Records {@see EmployerRegistered}.
     */
    public static function track(
        EmployerAccountId $id,
        TenantId $tenantId,
        ExternalReference $externalReference,
        EmployerReference $employer,
        Instant $now,
    ): self {
        return (new self(
            $id,
            $tenantId,
            $externalReference,
            $employer,
            EmployerOrigin::Tracked,
            $now,
            EmployerTracking::tracked($now),
        ))->recordRegistration();
    }

    /**
     * Existing Pajemploi employer adopted by the platform. Records {@see EmployerRegistered}.
     */
    public static function import(
        EmployerAccountId $id,
        TenantId $tenantId,
        ExternalReference $externalReference,
        EmployerReference $employer,
        Instant $now,
    ): self {
        return (new self(
            $id,
            $tenantId,
            $externalReference,
            $employer,
            EmployerOrigin::Imported,
            $now,
            EmployerTracking::tracked($now),
        ))->recordRegistration();
    }

    public function id(): EmployerAccountId
    {
        return $this->id;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function externalReference(): ExternalReference
    {
        return $this->externalReference;
    }

    public function employer(): EmployerReference
    {
        return $this->employer;
    }

    public function employerNumber(): PajeEmployerNumber
    {
        return $this->employer->number();
    }

    public function birthDate(): CalendarDate
    {
        return $this->employer->birthDate();
    }

    public function origin(): EmployerOrigin
    {
        return $this->origin;
    }

    public function createdAt(): Instant
    {
        return $this->createdAt;
    }

    public function tracking(): EmployerTracking
    {
        return $this->tracking;
    }

    public function stage(): EmployerStage
    {
        return $this->tracking->stage();
    }

    /**
     * Last method 020 snapshot, with {@see self::lastCheckedAt()} the instant it was read.
     */
    public function lastVerification(): ?EmployerVerification
    {
        return $this->tracking->lastVerification();
    }

    public function lastCheckedAt(): ?Instant
    {
        return $this->tracking->lastCheckedAt();
    }

    public function updatedAt(): Instant
    {
        return $this->tracking->updatedAt();
    }

    /**
     * Method 030 succeeded (or a mandate was imported). Records {@see EmployerStageChanged} when the stage changes.
     */
    public function markMandateRegistered(Instant $now): void
    {
        $this->changeStage($this->tracking->stage()->afterMandateRegistered(), $now);
    }

    /**
     * Method 031 succeeded. Records {@see EmployerStageChanged} when the stage changes.
     */
    public function markMandateCancelled(Instant $now): void
    {
        $this->changeStage($this->tracking->stage()->afterMandateCancelled(), $now);
    }

    /**
     * Method 020 answered. Records {@see EmployerStatusRefreshed} on the first snapshot or when the snapshot differs from
     * the last one; otherwise only the check instants are refreshed. The stage is never changed (020 says nothing about
     * the mandate).
     */
    public function applyVerification(EmployerVerification $verification, Instant $now): void
    {
        $refreshed = !$this->tracking->hasVerification($verification);
        $this->tracking = $this->tracking->withVerification($verification, $now);
        if ($refreshed) {
            $this->events[] = new EmployerStatusRefreshed($this->id, $this->tenantId, $this->externalReference, $this->tracking->stage(), $verification, $now);
        }
    }

    /**
     * GDPR erasure (ForgetEmployer, ADR 0015): records {@see EmployerForgotten} with the ids erased with the account.
     * The state is not changed: the caller deletes the account, its mandates and its declaration records in the same
     * transaction, then releases the event.
     *
     * @param list<MandateId>           $mandateIds
     * @param list<DeclarationRecordId> $declarationRecordIds
     */
    public function forget(Instant $now, array $mandateIds, array $declarationRecordIds): void
    {
        $this->events[] = new EmployerForgotten($this->id, $this->tenantId, $this->externalReference, $mandateIds, $declarationRecordIds, $now);
    }

    /**
     * Returns the events recorded since the last call, and forgets them.
     *
     * @return list<DomainEvent>
     */
    public function releaseEvents(): array
    {
        $events = $this->events;
        $this->events = [];

        return $events;
    }

    private function recordRegistration(): self
    {
        $this->events[] = new EmployerRegistered($this->id, $this->tenantId, $this->externalReference, $this->origin, $this->createdAt);

        return $this;
    }

    private function changeStage(EmployerStage $stage, Instant $now): void
    {
        $from = $this->tracking->stage();
        $this->tracking = $this->tracking->withStage($stage, $now);
        if ($stage !== $from) {
            $this->events[] = new EmployerStageChanged($this->id, $this->tenantId, $this->externalReference, $from, $stage, $now);
        }
    }
}
