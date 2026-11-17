# ADR 0012 — Public integration events

Status: Accepted (2026-11-17). Relates to [ADR 0000](0000-reference-architecture.md),
[ADR 0006](0006-local-lifecycles.md), [ADR 0007](0007-personal-data.md).

## Context

With full parity, the package exposes versioned integration events through an outbox (reference `Contract\Event`).

## Decision

- `Contract\Event\IntegrationEvent` (abstract readonly, scalar properties `eventId`, `occurredAt`, `tenantId`,
  `aggregateId`, `externalReference`) and the v1 catalogue (every `VERSION = 1`):
  - `urssaf.paje.employer.status_changed` (020 snapshot: `authorised`, `onlineAccountState` raw value);
  - `urssaf.paje.mandate.registered`, `urssaf.paje.mandate.cancelled`;
  - `urssaf.paje.declaration.declared` (`referenceDocumentaire`, non-blocking codes),
    `urssaf.paje.declaration.rejected` (error codes), `urssaf.paje.declaration.already_declared`,
    `urssaf.paje.declaration.outcome_unknown` (reason);
  - `urssaf.paje.employer.forgotten` (ADR 0015: erased mandate and declaration record ids).
- Payloads carry package identifiers (aggregate ids, the host's `externalReference`, the employer account id),
  `referenceDocumentaire`, activity type, period, states and codes — never the Pajemploi numbers (credential-like,
  §4 decision 2), birth dates, NIR, names or amounts.
- No integration event for requeued declarations, failed submissions or rejected credentials: hosts list them with
  `FindDeclarations::inState()`.
- `IntegrationEventCodec` serialises with an allow-list of the catalogue classes.

## Consequences

- Documented in `docs/integration-events.md`; any payload change bumps `VERSION`.
