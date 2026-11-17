# ADR 0006 — Local lifecycles of employers, mandates and declarations

Status: Accepted (2026-11-17). Relates to [ADR 0000](0000-reference-architecture.md).

## Context

PAJE exposes no status for declarations and only the 020 snapshot for employers (`employeurAutoriseOk`,
`etatCompteEnLigne`); unlike CESU, 020 says nothing about the mandate. The reference drives its records through
explicit state machines with claims, attempts and reconciliation. The roadmap asks the Doctrine bridge to track
employers, mandates and declarations "to track what was sent and protect against double declaration".

## Decision

- `Domain\Employer\EmployerAccount` (aggregate, per tenant and `EmployerReference` = Pajemploi number + birth date):
  keeps the last `EmployerVerification` snapshot (020) with the instant it was read, its origin (`tracked` by a use
  case or `imported`), and the host's `ExternalReference` (unique per tenant). Its stage is derived:
  `tracked` → `mandate_registered` (030 succeeded) → `mandate_cancelled` (031). Stages never block a call by
  themselves (ADR 0009 does).
- `Domain\Mandate\Mandate` (aggregate, per tenant and employer): `registered` → `cancelled`. Registration happens after
  a successful 030 (`RegisterMandate`) or by `ImportMandate` (no URSSAF call) for an employer whose mandate was
  registered outside the package. At most one non-cancelled mandate per (tenant, employer); registering again after a
  cancellation creates a new mandate. `COMPTE_DEJA_MANDATE` on 030 creates nothing and is reported as
  `mandate_held_elsewhere`.
- `Domain\Declaration\DeclarationRecord` (aggregate): `pending` → `submitting` (atomic claim) → `declared` (with
  `DeclarationResult`) | `rejected` (final, errors kept) | `already_declared` (final) | `outcome_unknown` (ADR 0003) |
  `error` (retryable failure proven not sent, back to `pending` until `workflow.max_submission_attempts`, default 5) |
  `invalid_credentials` (requeued by `RequeueAfterInvalidCredentials`). Allowed transitions:
  `pending → submitting`; `submitting → declared | rejected | already_declared | outcome_unknown | error |
  invalid_credentials`; `error → pending`, `invalid_credentials → pending` (requeue); `outcome_unknown → declared`
  (`ResolveDeclarationOutcome`, with the `referenceDocumentaire` read on pajemploi) and `outcome_unknown → pending`
  (not received). Every other transition throws `InvalidStateTransition`.
- `DeclareSalary` creates the record, claims it and submits it synchronously (050 or 051 by variant). The record never
  holds the payload (ADR 0007): a retry is the host calling `DeclareSalary` again with the same external reference and
  declaration, checked by a `DeclarationFingerprint` (SHA-256 of the canonical declaration **without** personal data:
  activity type, period, payment date, amounts, hours, days, flags). Re-entry table:
  same content in `declared` / `already_declared` / `outcome_unknown` / `invalid_credentials` → `replayed` (no call);
  same content in `pending` or `error` with attempts left → sent; in `submitting` → `not_claimed`; different content →
  `DeclarationAlreadyRecorded`; a `rejected` record is superseded (deleted, new id) by the next `DeclareSalary` with
  the same external reference, whatever its content. An `error` record that exhausted its attempts is reported
  `exhausted` without calling URSSAF.
- Reads (020, 021, 022, 040, 041) and pre-declarations (045, 046) are queries: they record nothing, except
  `SyncEmployerStatus` / `VerifyEmployer` on a tracked employer, which refresh the snapshot. 023 (`AssociateEmployee`)
  is a synchronous command without a local aggregate.
- `SyncEmployerStatus` (020) is the only polling.

## Consequences

- Unique constraints: employers `(tenant, employer number)` and `(tenant, external reference)`, mandates one
  non-cancelled per `(tenant, employer)`, declarations `(tenant, external reference)`.
- Two declarations differing only by personal data (child names, employee identity) share a fingerprint; the external
  reference is the host's idempotency key.

## Amendment (2026-11-17, lead-accepted, lot 4a)

- **Names:** the employer origin is `EmployerOrigin` (`tracked` | `imported`) and the mandate origin `MandateOrigin`
  (`registered` | `imported`); factories `EmployerAccount::track()` / `::import()` and `Mandate::register()` /
  `::import()`. `EmployerStage` is `tracked` | `mandate_registered` | `mandate_cancelled`; `MandateState` is
  `registered` | `cancelled`.
- **Stage:** method 020 never changes the stage (it says nothing about the mandate): `EmployerAccount::applyVerification()`
  only stores the last `EmployerVerification` snapshot and the instant it was read. Registering a mandate (030 or import)
  moves any stage to `mandate_registered`, cancelling (031) to `mandate_cancelled`.
- **Keys:** repositories look employers and mandates up by `(TenantId, PajeEmployerNumber)`; the birth date is stored in
  the `EmployerReference` (ADR 0007) but is not part of any key.
- **Events:** domain events carry identifiers only (ids, tenant, external reference, origin, stages, the 020 snapshot): never
  the employer number or birth date (ADR 0012). Importing an employer or a mandate records the same event as creating it
  (`EmployerRegistered` / `MandateRegistered`, with the origin).

## Amendment (2026-11-17, lead-accepted, lot 4a, declarations)

- **Fingerprint:** `DeclarationFingerprint::of(Declaration)` hashes (SHA-256) a canonical text of the declaration: activity
  type, period and payment dates, hours, paid-leave half-days, rates, amounts and indemnities, the childminder or home
  care fields (calculation mode included), the contract end, the number of children and, for a childminder, the per-child
  care day counts (`joursAccueilHandicap`, `nbJoursAccueil24h`) without the child identity. The employee identification
  (kind, number, NIR, names, birth dates), the employer and the child names are not part of it.
- **Record:** `DeclarationSnapshot` keeps the employer number, activity type, period, employee identification kind and
  optional `PajeEmployeeNumber`, and the fingerprint. The result kept once `declared` is a `DeclarationResult` **without
  child checks** (child names, ADR 0007); it keeps the amounts, rate, `referenceDocumentaire`, transfer date and
  non-blocking messages. A declared record always has a result. `SubmissionError` keeps the error code, its kind (the value
  of `RejectionKind`, ADR 0005), message and description.
- **Resolution:** `outcome_unknown → declared` stores a result holding only the `referenceDocumentaire` read on
  pajemploi (`DeclarationRecord::resolveAsDeclared(string, Instant)`, a blank reference is refused).
- **Events:** the domain events translated into integration events (declared, rejected, already declared, outcome
  unknown) carry the record id, tenant, external reference, activity type and period; the others (recorded, requeued,
  submission failed, credentials rejected) carry the record id and tenant only (never the
  employer number, ADR 0012); `DeclarationDeclared` also carries the `referenceDocumentaire` and the codes of the
  non-blocking messages, never an amount.
- **Port:** `DeclarationRecordRepository` has no `findByDeclarationId` (PAJE has no declaration id); `countByEmployer()` and
  `deleteByEmployer()` take the `PajeEmployerNumber` (ADR 0015).

## Amendment (2026-11-17, lead-accepted, lot 4a, review)

- **Events are self-sufficient:** translating a domain event into an integration event is a pure function of the event,
  with no repository lookup, so it cannot fail inside the transaction (a rollback after a successful 030 / 031 would lose
  a write that is never resent, ADR 0003). `Mandate` keeps the `EmployerAccountId` of its employer (set by `register()` /
  `import()`: the use case tracks the employer first); `MandateRegistered` and `MandateCancelled` carry it.
  `EmployerStatusRefreshed` carries the stage of the account. `DeclarationRecord` keeps the optional
  `EmployerAccountId` given to `record()` (null when the employer is not tracked then); the declared, rejected,
  outcome-unknown and already-declared events carry it.
- **Codes only:** `DeclarationRejected`, `DeclarationAlreadyDeclared` and `DeclarationSubmissionFailed` carry the error
  codes (`list<string>`), never `SubmissionError` (its message and description may echo host data, ADR 0007). The codes
  are those of the errors at the time of the transition.
- **Resolved result:** the result stored by `resolveAsDeclared()` has `pajemploiPlus = false` by convention (only the
  `referenceDocumentaire` was read by the operator, the flag is unknown); do not read it as a fact.
- **No employer number in exceptions:** the employer number is credential-like (ADR 0007): the `InvariantViolation` of a
  duplicate employer account or active mandate carries no parameter (the Doctrine repositories follow the same rule).
