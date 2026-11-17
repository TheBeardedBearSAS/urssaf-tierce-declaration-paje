# Workflow

How the package follows an employer, its mandate and its declarations, and what it does when a call fails. The rules
come from the ADRs ([0003](adr/0003-retry-and-unknown-outcome.md), [0006](adr/0006-local-lifecycles.md),
[0008](adr/0008-results-and-predeclaration-check.md), [0009](adr/0009-guards-and-opt-outs.md),
[0015](adr/0015-employer-erasure.md)); this page is the summary. The URSSAF contract is in
[api-reference.md](api-reference.md).

PAJE has no idempotency key and no operation to read, amend or cancel a declaration: a repeated declaration is
rejected, and a declaration whose answer was lost is never resent. The local records exist to track what was sent and
to protect against a double declaration.

## Lifecycles

### Employer account

`tracked` -> `mandate_registered` (030 succeeded, or `ImportMandate`) -> `mandate_cancelled` (031 succeeded).

- `TrackEmployer` (method 020, then the account with its first snapshot) and `ImportEmployer` (no call, for an employer
  already known to the host) create the account, unique per tenant on the external reference and on the Pajemploi
  number.
- Method 020 never changes the stage (it says nothing about the mandate): `SyncEmployerStatus` and `VerifyEmployer`
  only refresh the snapshot (`authorised`, online account state) and the instant it was read.
- `SyncEmployerStatus` is the only polling, and `SyncEmployerStatuses` runs it over a tenant.

### Mandate

`registered` -> `cancelled`. At most one non-cancelled mandate per tenant and employer.

- `RegisterMandate` (030) records the mandate after URSSAF accepted it. `ImportMandate` records a mandate obtained outside
  the package (no call).
- **Both are idempotent and complete the account.** An active local mandate is never registered again (no call), and when
  the account stage is not `mandate_registered` yet (a mandate saved by another writer, a failed recording), the account is
  completed without any call: `RegisterMandate` and `ImportMandate` both end with an active mandate and an account at
  `mandate_registered`, an `EmployerStageChanged` event being recorded when the stage changes. When both are already
  consistent nothing is written.
- `CancelMandate` (031) needs an active local mandate (`mandate.not_found` otherwise: import it first).
  `RecordMandateCancellation` (no call) cancels the active mandate, or, when the latest mandate is already cancelled,
  completes the account (`mandate_cancelled`, only when no other mandate is active); idempotent too.
- `COMPTE_DEJA_MANDATE` (another body holds the mandate) is an `UrssafRequestRejected` of kind `mandate_held_elsewhere`
  and records nothing.
- **After an unknown outcome on 030 or 031 nothing changes locally: check on pajemploi.urssaf.fr first.**
  - 030: if the mandate is registered there, call `ImportMandate` (no call); only if it is not, call `RegisterMandate` again.
  - 031: if the mandate is cancelled there, call `RecordMandateCancellation` (no call); only if it is still active, call
    `CancelMandate` again.
- **Which exception means what.** Only `MandateRecordingFailed` (`mandate.recording_failed`) means that URSSAF accepted
  030 / 031: recover without any call (`ImportMandate` / `RecordMandateCancellation`), never call the use case again.
  Every other exception means "not sent" (`RecordNotFound` raised before the call, a validation failure, `UrssafRequestRejected`,
  `UrssafAuthenticationFailed`, `UrssafUnavailable`, `RateLimitExceeded`, a missing credentials record) or "unknown,
  check pajemploi first" (`OutcomeUnknown`, `UnexpectedUrssafResponse` such as an unexpected 404, any foreign
  throwable). The branch of `RegisterMandate` that completes an account without any call (a mandate is already active)
  uses the import path: it never reports an operation it did not call.
- **Serialize the mandate commands of one employer** (`RegisterMandate`, `ImportMandate`, `CancelMandate`,
  `RecordMandateCancellation`): the host takes a lock per employer (the Symfony bridge provides it). Without it, two
  processes can both pass the "no active mandate" check. The lock does not cover the status refresh
  (`SyncEmployerStatus(es)`, `VerifyEmployer`), which can save the account at any time.
- **Once URSSAF accepted 030 or 031, the local transition is always recorded without another call.** After the call,
  `RegisterMandate` and `CancelMandate` read the account and the mandate again in a new transaction and apply the transition,
  up to 3 attempts, whenever the save conflicts (`employer_account.concurrent_modification`,
  `mandate.concurrent_modification`, `mandate.duplicate_active`). A transition another writer already recorded is
  returned as is. If every attempt failed, or a recording attempt failed for another reason (a violation that is not a
  conflict, a storage or outbox failure), `MandateRecordingFailed` (`mandate.recording_failed`, the original failure is its
  previous exception, the parameters give the method, the attempts made and the code or class of the last failure) is
  thrown: one signal for the host, the call was made, **never call the use case again**. Record the result without a call:
  `ImportMandate` after a failed 030, `RecordMandateCancellation` after a failed 031 (both are idempotent). An
  account or mandate erased after the accepted call is wrapped too (`employer_account.not_found` / `mandate.not_found` as last
  code, the `RecordNotFound` as previous exception): only `MandateRecordingFailed` means 030 / 031 was accepted.
- `ImportMandate` and `RecordMandateCancellation` make no call. `ImportMandate` reads again and retries like the others
  (3 attempts) and, if every attempt conflicted, throws the last conflict violation (calling it again is safe).
- These rollbacks need a transactional `TransactionManager`, see [Transactions](standalone.md#transactions).

### Declaration record

```
pending -> submitting -> declared
                      -> rejected            (final; superseded by the next DeclareSalary of the same reference)
                      -> already_declared    (final)
                      -> outcome_unknown -> declared | pending
                      -> error -> pending    (retryable failure, attempts left)
                      -> invalid_credentials -> pending   (RequeueAfterInvalidCredentials)
```

Late acceptance: an accepted 050 / 051 is never discarded, so `declared` is also reachable from `pending`, `error`,
`invalid_credentials`, `submitting` (claimed again), `outcome_unknown`, `rejected` and `already_declared`, see
[the settlement table](#settlement-table-the-answer-arrives-on-another-state). Only `declared` is a dead end.

`DeclareSalary` creates the record, claims it atomically (`pending` -> `submitting`) and sends it synchronously (050 for
a childminder, 051 for home childcare). The URSSAF call is made outside any transaction; its outcome is then applied in
a transaction of its own. The record never holds the payload ([ADR 0007](adr/0007-personal-data.md)): to retry, the host
calls `DeclareSalary` again with the same external reference and the same declaration.

## Outcome table

What `DeclareSalary` does with the answer of 050 / 051 (and, when the pre-declaration check is on, of 045 / 046, see
below).

| Answer | Record | `ItemOutcome` | Notes |
|---|---|---|---|
| 200 | `declared` | `declared` | the result keeps the amounts, the `referenceDocumentaire`, the transfer date and the non-blocking messages |
| 400 duplicate (`ER_API_050_0070` childminder, `ER_API_DECLA_00020` home care) | `already_declared` | `already_declared` | final, no `referenceDocumentaire`; counted under `needingAttention()` (the declaration may come from another channel) |
| any other 400 | `rejected` | `rejected` | final, the errors are kept; a generic `urssaf.rejected` error when URSSAF gave no code |
| 401, 403, 512 | `invalid_credentials` | `invalid_credentials` | fix the credentials, then `RequeueAfterInvalidCredentials` |
| transport error, 5xx other than 512, unreadable 2xx | `outcome_unknown` | `outcome_unknown` | never resent, see below |
| 429, token failure, local rate limit (provably not sent) | `error`, then `pending` | `RetryableUrssafFailure` is thrown after the commit (`retryAfterSeconds()`) | after `workflow.max_submission_attempts` (default 5): `exhausted`, record left in `error` |
| another package exception | `error` / `pending` | rethrown unchanged | |
| any other throwable (a bug, a foreign gateway) | `outcome_unknown` | rethrown | it may have been sent |

`SubmissionError::kind` is the `RejectionKind` of the rejection (`technical` for an unavailable service or an unknown
outcome, `unknown` for an unexpected response). The synthetic codes (`urssaf.unavailable`, `urssaf.unexpected_response`,
`urssaf.unexpected_failure`, `urssaf.rejected`) never carry the message of a foreign exception.

## Re-entry table

What `DeclareSalary` does when the external reference already has a record. Content is compared with the
`DeclarationFingerprint` (SHA-256 of the declaration without personal data) and with the employer number.

| Existing record | Same content and employer | Different content or employer |
|---|---|---|
| `declared`, `already_declared`, `outcome_unknown`, `invalid_credentials` | `replayed`, no call | `DeclarationAlreadyRecorded` |
| `pending`, `error` with attempts left | sent | `DeclarationAlreadyRecorded` |
| `error`, attempts exhausted | `exhausted`, no call | `DeclarationAlreadyRecorded` |
| `submitting` | `not_claimed` (another process is sending) | `DeclarationAlreadyRecorded` |
| `rejected` | superseded: deleted, new record, sent | superseded, whatever the content |

Two declarations that differ only by personal data (child names, employee identity) share a fingerprint: the external
reference is the host's idempotency key.

### Concurrent calls

The repositories refuse to save a record that another writer changed since it was read (optimistic concurrency,
`declaration_record.concurrent_modification`), and the claim is a compare-and-set, so two concurrent calls never send
the same declaration twice. The call that loses a race sends nothing:

- **Two first calls for the same new external reference:** the uniqueness constraint
  (`declaration_record.duplicate_external_reference`) refuses the second record. `DeclareSalary` does not throw: it rolls
  back its transaction and returns `not_claimed` with the record the first call created.
- **Two calls requeuing the same `error` record**, or a requeue racing another writer: the save is refused, the call
  returns `not_claimed` with the current record, nothing is sent. When the record was erased meanwhile
  (`ForgetEmployer`), there is no record to return: the `declaration_record.concurrent_modification` violation is thrown.
- **A record changed while its call was in flight:** see the settlement table below.
- **Batch use cases:** `ReconcileStaleDeclarations` skips a record that changed since it was read and reports it
  `not_claimed` with the code `declaration_record.concurrent_modification`; `SyncEmployerStatuses` reports an account
  that changed `retry_later` with `employer_account.concurrent_modification`. `ResolveDeclarationOutcome`,
  `RequeueAfterInvalidCredentials` (the whole transaction, nothing is requeued) and `SyncEmployerStatus` throw the
  violation: read again and decide again. "Nothing is requeued" and every other rollback in this page need a
  transactional `TransactionManager`: with the default `ImmediateTransactionManager` (in-memory repositories) nothing is
  rolled back and the records saved before the conflict stay requeued, see [Transactions](standalone.md#transactions).
  The unit tests check both: a transactional fake that restores the state on failure, and the immediate manager.

### Settlement table: the answer arrives on another state

Only the process that claimed a record (`DeclareSalary` through the submitter) and `ReconcileStaleDeclarations` (to
`outcome_unknown`) move it out of `submitting`. After a reconciliation an operator may resolve the record (`declared` or
back to `pending`) and a later `DeclareSalary` may claim it again (the attempt counter then increases). A claim is
identified by the attempt number it took, and the answer of a call is settled against the record **as re-read when the
answer arrives**:

| State when the answer arrives | Accepted (200 of 050 / 051) | Definitive 400 (duplicate or other) | Unknown outcome / unexpected failure | Proven not sent (429, token, rate limit, 401/403) |
|---|---|---|---|---|
| `submitting`, same attempt (own claim) | `declared` | `already_declared` / `rejected` | `outcome_unknown` | `error`, then `pending` while attempts remain (`exhausted`: left in `error`) / `invalid_credentials` |
| `outcome_unknown` (reconciled meanwhile) | `declared` | unchanged | unchanged | unchanged |
| `pending`, `error`, `invalid_credentials` (resolved as not received, requeued) | `declared` (late acceptance) | unchanged | unchanged | unchanged |
| `submitting`, later attempt (claimed again) | `declared` (the later call will then be answered by a duplicate and leaves `declared` unchanged) | unchanged | unchanged | unchanged |
| `already_declared`, `rejected` | `declared` (late acceptance: the reference of our own call wins) | unchanged | unchanged | unchanged |
| `declared` | unchanged (first result kept) | unchanged | unchanged | unchanged |
| erased (`ForgetEmployer`) | `DeclarationRecordingFailed` carrying the `referenceDocumentaire` (no local record left) | nothing | nothing | nothing |

- **An accepted answer is never discarded.** It is recorded on any surviving state except `declared` (the domain allows
  `declared` from every non-final and every "lost race" state for that purpose, same `DeclarationDeclared` event).
  `DeclareSalary` returns `declared`. A save of the accepted result that fails for another reason (storage, outbox,
  conflicts that outlast 3 attempts) throws `DeclarationRecordingFailed` (`declaration_record.recording_failed`,
  `documentReference()` is the `referenceDocumentaire`, the previous exception is the original failure): the call was
  accepted, **it is never resent**. Run `ReconcileStaleDeclarations` (the record leaves `submitting`) then
  `ResolveDeclarationOutcome::asDeclared()` with that reference. Without a transactional `TransactionManager` the save may
  have gone through before the failure (an outbox failure): if the record is already `declared` there is nothing to do.
  Exactly, after an outbox failure on an accepted answer: with a transactional manager the save is rolled back, the record
  stays `submitting` and a follow-up `DeclareSalary` of the same reference returns `not_claimed` without any call (reconcile
  then resolve, as above); with `ImmediateTransactionManager` the record is `declared` (only its integration events are
  lost) and the follow-up returns `replayed`. In both cases the gateway was called once.
- **After the erasure of the record** (`ForgetEmployer` while the call is in flight) there is no local record: a follow-up
  `DeclareSalary` of the same reference records the declaration anew and calls URSSAF again. When URSSAF holds the
  declaration it answers the duplicate code and the new record ends `already_declared`; two calls were made in total.
- **Any other answer only settles its own claim.** On a record it no longer owns it changes nothing and the caller gets the
  re-entry outcome of the current state (`not_claimed` when the record is `submitting`, `pending` or `error`, `replayed`
  otherwise), without any exception (`RetryableUrssafFailure` included: the record is not pending because of this call).
- **Residual case.** A call accepted at URSSAF whose answer is never received (process crash) leaves `submitting`, then
  `outcome_unknown`; if an operator wrongly resolves it as not received, the next `DeclareSalary` is answered by the
  duplicate code (`ER_API_050_0070` / `ER_API_DECLA_00020`, [api-reference.md](api-reference.md)) and ends
  `already_declared` without its reference: URSSAF never records a second volet social.
- **Configuration.** `workflow.stale_submission_after` must exceed the HTTP client timeout (plus the token call), so that
  reconciling a live call is a misconfiguration (it is enforced as a bundle configuration rule in the Symfony lot).

## Unknown outcomes: reconcile and resolve

A never-resent operation (050, 051) whose answer is lost leaves the record `outcome_unknown` with a reason
(`may_have_reached_urssaf`, `stale_submission`). The package never resends it.

1. `ReconcileStaleDeclarations` moves the records left `submitting` for longer than `workflow.stale_submission_after`
   (default `PT15M`: a crashed process) to `outcome_unknown` with reason `stale_submission`, and reports them
   `manual_review`.
2. An operator checks on pajemploi.urssaf.fr whether the declaration exists.
3. `ResolveDeclarationOutcome::asDeclared($tenant, $selector, $referenceDocumentaire)` records the declaration with the
   `referenceDocumentaire` read there (a blank one is refused); `asNotReceived()` puts the record back to `pending`, and
   the host calls `DeclareSalary` again. Any other state is refused (`InvalidStateTransition`).

Resolved as declared, the result holds only the `referenceDocumentaire` (`pajemploiPlus` is `false` by convention).

## Pre-declaration check (opt-in)

`WorkflowPolicy::withPreDeclareBeforeDeclaring(true)` makes `DeclareSalary` call 045 / 046 with the same payload right
after the claim. A failed check settles the record like the table above, except that it never gives an unknown outcome
(045 / 046 record nothing): a duplicate code -> `already_declared`; another 400 -> `rejected` (050 / 051 is not called);
401, 403, 512 -> `invalid_credentials`; a retryable failure (429, token failure, local rate limit, transport error or 5xx,
even an `UrssafUnavailable` that may have reached URSSAF) -> `error`, then `pending` while attempts remain
(`RetryableUrssafFailure` thrown after the commit) or `exhausted`; an `OutcomeUnknown`, another package exception or any
other throwable -> `error`, then `pending` while attempts remain, rethrown unchanged. It costs one more call per
declaration.
`PreDeclareSalary` is the same dry run as a query.

## Guards

- **Mandate guard**, off by default: `WorkflowPolicy::withRequireLocalMandate(true)` makes `DeclareSalary` and
  `PreDeclareSalary` refuse (`MandateRequired`, before any record or call) when the employer has no active local mandate.
  A command can opt out with `skipMandateCheck`. `MANDAT_INEXISTANT` from URSSAF is always reported as `mandate_missing`.
- **023 identity:** `AssociateEmployee` with an `EmployeeByAdministrativeData` identification requires a non-empty
  `EmployeeCreation::identity()` (`creation.identity`, code `employee_association.identity_required`).
- **Erasure:** `ForgetEmployer` deletes the account, its mandates and its records in one transaction, nothing is sent to
  URSSAF. It refuses an active mandate, a `submitting` record and an `outcome_unknown` record, unless the command or
  the policy allows it (`allowActiveMandate`, `allowUnknownOutcomes`; a `submitting` record is always refused).

## Safety crons

The package never schedules anything. Run these yourself (cron, worker); two runs of the same use case must not
overlap (use a lock of your own).

| Use case | Frequency | Why |
|---|---|---|
| `ReconcileStaleDeclarations` | every 15 minutes | records stuck in `submitting` after a crash |
| `SyncEmployerStatuses` (per tenant) | daily | follows the employer accounts (020 is cheap, not free) |
| `RequeueAfterInvalidCredentials` (per tenant) | once the credentials are fixed | then call `DeclareSalary` again |
| `RelayOutbox` | every minute | only with an outbox, see [integration-events.md](integration-events.md) |
| `PurgeOutbox` | daily | retention `P30D` by default |

`GetWorkflowOverview` tells where to look: `needingAttention()` counts the `outcome_unknown`, `error`,
`invalid_credentials` and `already_declared` declarations.
