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

## Amendment (2026-11-17, lead-accepted, lot 4b)

How a declaration answer and a mandate call are settled is governed by the settlement table amendment below, which prevails
wherever it disagrees with this section.

### Employers and mandates

- **Selector:** an employer account is designated by `EmployerSelector` (account id, host external reference or Pajemploi
  number), always within the tenant; an unknown or foreign account is `RecordNotFound` (`employer_account.not_found`, the
  identifier of a number selector is masked).
- **Tracking:** `TrackEmployer` (020, then the account with its first snapshot) and `ImportEmployer` (no call) share one
  rule, unique per tenant on the external reference and on the employer number: the same external reference with the
  same employer (number and birth date) returns the tracked account with no call; another employer under that reference,
  or the number tracked under another reference, is `EmployerAlreadyTracked` (`employer_account.already_tracked`, no
  number in the message or parameters), before any call.
- **Mandate commands:** `RegisterMandate` (030), `ImportMandate` (no call), `CancelMandate` (031) and
  `RecordMandateCancellation` (no call) locate the account first. A local active mandate makes `RegisterMandate` and
  `ImportMandate` idempotent (returned as is, no call). `CancelMandate` without a local active mandate is refused before
  any call (`RecordNotFound`, `mandate.not_found`, identified by the account id): record the mandate with `ImportMandate`
  first. The URSSAF call is made outside the transaction; a call that fails (rejection, unknown outcome, temporary failure)
  records and cancels nothing. `COMPTE_DEJA_MANDATE` reaches the host as the `UrssafRequestRejected` whose `kind()` is
  `mandate_held_elsewhere`. Registering, importing or cancelling a mandate saves the mandate and the account in one
  transaction and records both aggregates' events.
- **Serialization:** the mandate commands of one employer run under a lock per employer (the Symfony bridge will provide
  it). The lock does not cover the status refresh: `SyncEmployerStatus`, `SyncEmployerStatuses` and `VerifyEmployer` save the
  account without it, so the account can change between the read and the save of a mandate command, besides a second
  mandate command that skipped the lock.
- **Once URSSAF accepted 030 or 031, the local transition is recorded without another call.** The `MandateRecorder` service
  makes at most 3 attempts. Each one reads the account and the mandate again in a new unit of work and applies the
  transition to what it read; `employer_account.concurrent_modification`, `mandate.concurrent_modification` and
  `mandate.duplicate_active` start the next attempt. A transition that another writer already recorded is not applied
  again (the mandate or the cancellation is returned as is), and an account left behind is completed (its stage follows
  the mandate). Without a transactional `TransactionManager` a failed attempt is not rolled back (the aggregates saved
  before the conflict stay saved) and the next attempt completes them.
- **One signal after an accepted call.** When every attempt conflicted, or an attempt failed for another reason (an
  `InvariantViolation` that is not a conflict, a storage or outbox failure), `MandateRecordingFailed`
  (`mandate.recording_failed`; parameters: the method, the attempt count, the last violation code or failure class) is
  thrown with the original failure as previous exception. **It is the only exception of `RegisterMandate` and
  `CancelMandate` that means that URSSAF accepted the call**; the recovery is call-free and never "call it again":
  `ImportMandate` after a failed 030, `RecordMandateCancellation` after a failed 031. A `RecordNotFound` after the
  accepted call (the account or the mandate was erased in between) is wrapped too (last code `employer_account.not_found` /
  `mandate.not_found`, the `RecordNotFound` as previous exception); only the call-free `ImportMandate` lets
  it through as is.
- **The mandate commands complete the account without any call.** `RegisterMandate` and `ImportMandate` find an active
  local mandate: when the account stage is not `mandate_registered` (a mandate saved by another writer, a failed or
  non-transactional recording), the `MandateRecorder` completes the account (same attempts and conflict rules,
  `EmployerStageChanged` recorded) through the import path, so a failure there is never reported as an accepted 030;
  when both are consistent nothing is written. `RecordMandateCancellation` does the same for a mandate that is already
  cancelled (`mandate_cancelled`, only when no other mandate is active). `ImportMandate` makes no call, reads again and
  retries up to 3 attempts; if every attempt conflicted it throws the last conflict violation (it is not a
  `MandateRecordingFailed`); calling it again is safe, it is idempotent.
- **After an unknown outcome** of 030 / 031 nothing changes locally: check pajemploi first. Mandate registered there:
  `ImportMandate`; cancelled: `RecordMandateCancellation`; only when the mandate is not in the state the call aimed at,
  call `RegisterMandate` or `CancelMandate` again.
- **Snapshot refresh:** `SyncEmployerStatus` and `VerifyEmployer` (on an employer tracked by the tenant with the same
  number and birth date) store the 020 answer through `EmployerStatusRefresher` (a changed snapshot records
  `EmployerStatusRefreshed`; an unchanged one only moves the check instant). `SyncEmployerStatuses` reports per account
  `changed`, `unchanged`, `failed` (400, with the error codes) or `retry_later` (any `RetryableException`: 429, transport,
  5xx, token failure, local rate limit) and goes on; a credential failure (`UrssafAuthenticationFailed`) concerns the whole
  tenant and stops the run, what was processed stays committed.
- **Views:** read models are primitives (`EmployerAccountView`, `MandateView`, `EmployerStatusView`, ...); the employer
  number is the only credential-like value they carry (`EmployerAccountView::$employerNumber`, masked in debug output),
  never the birth date.

### Declaration use cases

- **Re-entry, employer:** the fingerprint leaves the employer out, so `DeclareSalary` also compares the employer number of the
  recorded snapshot: the same external reference and content for **another employer** is a `DeclarationAlreadyRecorded`
  (except for a `rejected` record, superseded whatever its content), because the record would otherwise describe another
  employer than the one the declaration is sent for (and ADR 0015 erases by employer). The exception carries the external
  reference and the record id, never an employer number.
- **Record and tracked employer:** `DeclareSalary` looks the employer account up by `(tenant, employer number)` when it
  records the declaration and keeps its id (`null` when the employer is not tracked then); it neither tracks nor refreshes the
  employer.
- **Outcomes returned** (`DeclarationSubmission`: the `DeclarationView` of the record and an `ItemOutcome`): `declared`,
  `rejected`, `already_declared`, `outcome_unknown`, `invalid_credentials`, `exhausted`, `not_claimed` (record `submitting`,
  or the claim was lost), `replayed` (no call). The view carries the record id, tenant, external reference, optional employer
  account id, a snapshot summary (activity, period dates, employee identification kind and optional employee number,
  fingerprint: **no employer number**), and the status (state, attempts, instants, errors with their message and
  description, result, reason of an unknown outcome).
- **Resolution:** `ResolveDeclarationOutcome::asDeclared()` takes the `referenceDocumentaire` read on pajemploi (a blank one is
  refused with `document_reference.empty`), `asNotReceived()` puts the record back to `pending`; a record whose state is not
  `outcome_unknown` is refused (`InvalidStateTransition`).
- **Selectors:** a declaration is designated by record id or external reference (no URSSAF declaration id exists).
- **Attempts:** every claim consumes an attempt, including one that ends on a failed pre-declaration (ADR 0008).
- **Outbox:** `PurgeOutbox` has a nullable retention (`null` = `WorkflowPolicy::outboxRetention()`).

### Concurrency

- **Optimistic concurrency is part of the repository contract.** `save()` of `EmployerAccountRepository`,
  `MandateRepository` and `DeclarationRecordRepository` refuses a copy that another writer changed since it was read, with an
  `InvariantViolation` whose code is `employer_account.concurrent_modification`, `mandate.concurrent_modification` or
  `declaration_record.concurrent_modification` (constants `CONCURRENT_MODIFICATION` of the ports). For declaration records, a
  won `claimForSubmission()` is a change too: a copy read before the claim cannot be saved. The in-memory repositories keep a
  version per stored aggregate and per read or saved instance (`VersionTokens`); the Doctrine repositories use the version
  column of ADR 0014. The shared repository contract has the scenario, so every adapter must satisfy it.
- **Losing a race never sends twice.** `DeclareSalary` reports the loser of a concurrent first call for the same new external
  reference (`declaration_record.duplicate_external_reference`, transaction rolled back) and of a concurrent requeue as
  `ItemOutcome::NotClaimed` with the record the winner holds, instead of an exception: nothing is sent by that call. A record
  that changes while its 050 / 051 call is in flight is settled by the table below. `ReconcileStaleDeclarations` skips a
  record that changed and reports it `not_claimed` (code `declaration_record.concurrent_modification`);
  `SyncEmployerStatuses` reports an account that changed `retry_later` (code `employer_account.concurrent_modification`).
  `ResolveDeclarationOutcome`, `RequeueAfterInvalidCredentials` and `SyncEmployerStatus` let the violation propagate
  (transaction rolled back).
- **Late acceptance in the domain.** `DeclarationRecord::markDeclared()` also records an accepted answer on a record that is
  no longer the claim's own (`outcome_unknown`, `pending`, `error`, `invalid_credentials`, `submitting` of a later attempt,
  `already_declared`, `rejected`), with the same `DeclarationDeclared` event; only `declared` refuses it (the first result is
  kept). `rejected` and `already_declared` are therefore no longer dead ends of the state machine.

## Amendment (2026-11-17, lead-accepted, lot 4b, settlement table)

Written by the lead; it supersedes the lot 4b amendments of this ADR and of ADR 0003 wherever they disagree.

**Who can move a record out of `submitting`.** Only the process that claimed it (`DeclareSalary` through
`DeclarationSubmitter`) and `ReconcileStaleDeclarations` (to `outcome_unknown`). After a reconciliation an operator may
resolve the record (`declared` or back to `pending`) and a later `DeclareSalary` may claim it again (the attempt counter
then increases). A claim is identified by the attempt number it took; the answer of a call is settled against the
record **as re-read when the answer arrives**:

| State when the answer arrives | Accepted (200 of 050 / 051) | Definitive 400 (duplicate or other) | Unknown outcome / unexpected failure | Proven not sent (429, token, rate limit, 401/403) |
|---|---|---|---|---|
| `submitting`, same attempt (own claim) | `declared` | `already_declared` / `rejected` | `outcome_unknown` | `error`, then `pending` while attempts remain (`exhausted`: left in `error`) / `invalid_credentials` |
| `outcome_unknown` (reconciled meanwhile) | `declared` | unchanged | unchanged | unchanged |
| `pending`, `error`, `invalid_credentials` (resolved as not received, requeued) | `declared` (late acceptance) | unchanged | unchanged | unchanged |
| `submitting`, later attempt (claimed again) | `declared` (the later call will then be answered by a duplicate and leaves `declared` unchanged) | unchanged | unchanged | unchanged |
| `already_declared`, `rejected` | `declared` (late acceptance: the reference of our own call wins) | unchanged | unchanged | unchanged |
| `declared` | unchanged (first result kept) | unchanged | unchanged | unchanged |
| erased (`ForgetEmployer`) | `DeclarationRecordingFailed` carrying the `referenceDocumentaire` (no local record left) | nothing | nothing | nothing |

Rules:

- **An accepted answer is never discarded.** It is recorded on any surviving state except `declared`; the domain allows
  `declared` from every non-final and every "lost race" state for that purpose (late acceptance, same
  `DeclarationDeclared` event). A save of the accepted result that fails for another reason (storage, outbox, repeated
  conflicts after a bounded retry) throws `DeclarationRecordingFailed` (`declaration_record.recording_failed`) carrying
  the `referenceDocumentaire`: the call was accepted, it is never resent; the host runs `ReconcileStaleDeclarations`
  then `ResolveDeclarationOutcome::asDeclared()` with that reference.
- **Any other answer only settles its own claim.** On a record it no longer owns it changes nothing and the caller gets
  the re-entry outcome of the current state (`not_claimed`, `replayed`).
- **Residual case.** A call accepted at URSSAF whose answer is never received (process crash) leaves `submitting`, then
  `outcome_unknown`; if an operator wrongly resolves it as not received, the next `DeclareSalary` is answered by the
  duplicate code (`ER_API_050_0070` / `ER_API_DECLA_00020`, api-reference §2) and ends `already_declared` without its
  reference: URSSAF never records a second volet social.
- **Configuration.** `workflow.stale_submission_after` must exceed the HTTP client timeout (plus the token call), so
  that reconciling a live call is a misconfiguration; documented in `docs/workflow.md`, enforced as a bundle
  configuration rule in the Symfony lot.

Mandate commands: **only `MandateRecordingFailed` (`mandate.recording_failed`) means that 030 / 031 was accepted**;
recover with `ImportMandate` / `RecordMandateCancellation` (no call), which complete both the mandate and the account.
Every other exception means "not sent" (`RecordNotFound`, validation, `UrssafRequestRejected`, authentication,
`UrssafUnavailable`, `RateLimitExceeded`) or "unknown, check pajemploi first" (`OutcomeUnknown`,
`UnexpectedUrssafResponse`, any foreign throwable). The no-call completion branch of `RegisterMandate` uses the import
path and never reports an operation it did not call.
