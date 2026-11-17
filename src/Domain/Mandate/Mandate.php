<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\PajeEmployerNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvalidStateTransition;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\Event\MandateCancelled;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\Event\MandateRegistered;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\DomainEvent;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Instant;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;

/**
 * Aggregate root: local follow-up of the mandate given to the platform by an employer (methods 030, 031; ADR 0006).
 * Created with {@see self::register()} (after a successful 030) or {@see self::import()} (mandate registered outside the
 * package, no URSSAF call). A new registration after a cancellation is a new mandate (the application decides).
 *
 * The state machine is {@see MandateState}; invalid transitions throw {@see InvalidStateTransition}
 * ("mandate.invalid_state_transition"). Every state change records a domain event, see {@see self::releaseEvents()}.
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
class Mandate
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
        protected MandateId $id,
        private readonly TenantId $tenantId,
        private readonly ExternalReference $externalReference,
        private readonly EmployerReference $employer,
        private readonly EmployerAccountId $employerAccountId,
        private readonly MandateOrigin $origin,
        private MandateState $state,
        private MandateTimeline $timeline,
    ) {}

    /**
     * After a successful method 030: state "registered", origin "registered". Records {@see MandateRegistered}.
     *
     * @mago-expect lint:excessive-parameter-list
     */
    public static function register(
        MandateId $id,
        TenantId $tenantId,
        ExternalReference $externalReference,
        EmployerReference $employer,
        EmployerAccountId $employerAccountId,
        Instant $now,
    ): self {
        return (new self(
            $id,
            $tenantId,
            $externalReference,
            $employer,
            $employerAccountId,
            MandateOrigin::Registered,
            MandateState::Registered,
            MandateTimeline::registered($now),
        ))->recordRegistration($now);
    }

    /**
     * Mandate registered outside the package, adopted without any URSSAF call: state "registered", origin "imported".
     * Records {@see MandateRegistered}.
     *
     * @mago-expect lint:excessive-parameter-list
     */
    public static function import(
        MandateId $id,
        TenantId $tenantId,
        ExternalReference $externalReference,
        EmployerReference $employer,
        EmployerAccountId $employerAccountId,
        Instant $now,
    ): self {
        return (new self(
            $id,
            $tenantId,
            $externalReference,
            $employer,
            $employerAccountId,
            MandateOrigin::Imported,
            MandateState::Registered,
            MandateTimeline::registered($now),
        ))->recordRegistration($now);
    }

    public function id(): MandateId
    {
        return $this->id;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    /**
     * Host reference of the employer, carried by the integration events (ADR 0012).
     */
    public function externalReference(): ExternalReference
    {
        return $this->externalReference;
    }

    /**
     * The tracked employer account of the mandate, carried by the integration events (ADR 0012); the use case tracks the
     * employer before it registers or imports the mandate.
     */
    public function employerAccountId(): EmployerAccountId
    {
        return $this->employerAccountId;
    }

    public function employer(): EmployerReference
    {
        return $this->employer;
    }

    public function employerNumber(): PajeEmployerNumber
    {
        return $this->employer->number();
    }

    public function origin(): MandateOrigin
    {
        return $this->origin;
    }

    public function state(): MandateState
    {
        return $this->state;
    }

    public function timeline(): MandateTimeline
    {
        return $this->timeline;
    }

    public function registeredAt(): Instant
    {
        return $this->timeline->registeredAt();
    }

    public function cancelledAt(): ?Instant
    {
        return $this->timeline->cancelledAt();
    }

    public function updatedAt(): Instant
    {
        return $this->timeline->updatedAt();
    }

    /**
     * Not cancelled (used by the mandate guard, ADR 0009).
     */
    public function isActive(): bool
    {
        return $this->state->isActive();
    }

    /**
     * Method 031 succeeded: registered -> cancelled. Records {@see MandateCancelled}.
     *
     * @throws InvalidStateTransition "mandate.invalid_state_transition" when already cancelled
     */
    public function cancel(Instant $now): void
    {
        $this->state = $this->state->transitionTo(MandateState::Cancelled);
        $this->timeline = $this->timeline->withCancellation($now);
        $this->events[] = new MandateCancelled($this->id, $this->tenantId, $this->externalReference, $this->employerAccountId, $now);
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

    private function recordRegistration(Instant $now): self
    {
        $this->events[] = new MandateRegistered($this->id, $this->tenantId, $this->externalReference, $this->employerAccountId, $this->origin, $now);

        return $this;
    }
}
