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

## Amendment (2026-11-17, lead-accepted, lot 4a)

- Classes: `EmployerStatusChanged`, `EmployerForgotten`, `MandateRegistered`, `MandateCancelled`, `DeclarationDeclared`,
  `DeclarationRejected`, `DeclarationAlreadyDeclared`, `DeclarationOutcomeUnknown` (eight types, all `VERSION = 1`).
  `MandateRegistered` carries the `origin` (`registered` after 030, `imported`).
- `EmployerStatusChanged` carries `previousStage`, `stage` (`tracked`, `mandate_registered`, `mandate_cancelled`) and the
  raw 020 answer (`authorised`, `onlineAccountState`), both null for a change coming from a local mandate change.
- Declaration events carry `employerAccountId` (null when the employer is not tracked), `activityType`
  (`childminder`, `home_care`), `periodStart` and `periodEnd` (`yyyy-MM-dd`). `DeclarationDeclared` adds
  `referenceDocumentaire` and `nonBlockingCodes`; `DeclarationRejected` and `DeclarationAlreadyDeclared` add
  `errorCodes`; `DeclarationOutcomeUnknown` adds `reason`. Mandate events carry `employerAccountId`.
- `EmployerForgotten` carries `mandateIds` and `declarationRecordIds` only (no employer number).
- `Application\Dto\TenantOverview::needingAttention()` counts `outcome_unknown`, `error`, `invalid_credentials` and
  `already_declared` declarations (ADR 0003).
- `DeclarationDeclared::$referenceDocumentaire` is nullable: it is `null` when URSSAF answered without one (never an empty
  string). Integration events are built from the domain events alone (see the ADR 0006 review amendment).
