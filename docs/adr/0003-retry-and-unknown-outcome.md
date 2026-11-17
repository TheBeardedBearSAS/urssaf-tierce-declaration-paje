# ADR 0003 — Retries and unknown outcomes

Status: Accepted (2026-11-17). Relates to [ADR 0000](0000-reference-architecture.md).

## Context

PAJE has no idempotency key, no operation to read, amend or cancel a declaration, and a repeated declaration is
rejected (`ER_API_050_0070` AMA, `ER_API_DECLA_00020` GED) — api-reference §2, §4 decision 7. The reference reconciles
uncertain payment requests through a search (method 070); nothing equivalent exists here. The specification is silent
on the retry safety of 023 (it may **create** an employee), 030 and 031 (§5).

## Decision

- **Operation classes** (`Application\Port\Operation`):
  - *safe*: token, 020, 021, 022, 040, 041 (reads), 045, 046 (pre-declarations record nothing, PDF §3.9 / §3.10);
  - *never resent*: 050, 051;
  - *unspecified writes*: 023, 030, 031. Treated as never resent by default; `ApiConfiguration::retrySafeWrites()`
    (bundle `retry_safe_writes`) may list any of the three once the sandbox has shown them idempotent. 050 and 051
    cannot be listed (rejected at construction). An unknown outcome on 030 / 031 changes nothing locally (no mandate
    is recorded or cancelled): the exception reaches the caller, an operator checks on pajemploi, then calls
    `RegisterMandate` / `CancelMandate` again or records the mandate with `ImportMandate` (ADR 0006). On 023 the host
    simply calls `AssociateEmployee` again after checking (an already associated employee answers with the
    non-blocking `ER_API_023_NB_0000`).
- On a safe operation, a transport error, 429, 500, 502, 503 or 504 is a `UrssafUnavailable`
  (`RetryableException`), 429 with its `Retry-After`; any other 5xx except 512 (authentication, ADR 0002) is an
  `UnexpectedUrssafResponse`.
- On a never-resent operation, a transport error, any 5xx other than 512, or a 2xx answer that cannot be read
  (invalid JSON, missing field, invalid date) throws `OutcomeUnknown` (`mayHaveReachedUrssaf()` true, not retryable).
  429 and a local rate-limit refusal stay retryable: the call provably did not reach the service. A token failure is
  never an unknown outcome (the API call was not sent).
- Declarations: an unknown outcome moves the local record to `outcome_unknown`. `ReconcileStaleDeclarations` moves
  records left `submitting` past `workflow.stale_submission_after` (default PT15M) to `outcome_unknown` with reason
  `stale_submission`. An operator checks on pajemploi.urssaf.fr and records the result with
  `ResolveDeclarationOutcome` (declared, with its `referenceDocumentaire`, or not received → `pending`, then the host
  calls `DeclareSalary` again with the same declaration).
- The duplicate business errors (`ER_API_050_0070`, `ER_API_DECLA_00020`) map to `already_declared` (final, no
  `referenceDocumentaire`). The state means "URSSAF already holds a declaration for this key" (employee, period and
  child for AMA; employee and month for GED, §3.6), which may come from another channel (pajemploi.urssaf.fr, another
  record): it is counted under `needingAttention()` so that an operator checks it.
- Messenger (safeguard only: no message of the bundle calls a never-resent operation, ADR 0013): `OutcomeUnknown` and
  every non-retryable package exception are unrecoverable; `RetryableException` is recoverable with its delay.

## Consequences

- The package never produces an automatic duplicate declaration.
- Some declarations need a manual action; `GetWorkflowOverview` counts them under `needingAttention()`.
- The retry safety of 023 / 030 / 031 stays an open point (api-reference §5) settled by configuration, not code.

## Amendment (2026-11-17, lead-accepted, lot 4b)

- **Mandates:** `RegisterMandate`, `CancelMandate` and `AssociateEmployee` rethrow the `OutcomeUnknown` of 030 / 031 / 023 and
  change nothing locally; a `COMPTE_DEJA_MANDATE` rejection is the `UrssafRequestRejected` of kind `mandate_held_elsewhere`,
  also recording nothing. The batch `SyncEmployerStatuses` (020, a safe read) reports retryable failures per account
  (`ItemOutcome::RetryLater`) instead of stopping. After an unknown outcome on 030 / 031, check first, then record without a
  call: `ImportMandate` when pajemploi shows the mandate registered, `RecordMandateCancellation` when it shows it cancelled;
  call 030 / 031 again only when the mandate is not in the state aimed at. Only `MandateRecordingFailed` means that the call
  was accepted (ADR 0006).
- **Settlement of `DeclareSalary`:** the URSSAF call is made outside any transaction, after the atomic claim; its answer is
  then settled in a transaction of its own against the record as re-read at that time (ADR 0006, settlement table). For the
  claim's own record: `OutcomeUnknown` (and, from a foreign gateway, an `UrssafUnavailable` that `mayHaveReachedUrssaf()`) on
  050 / 051 → `outcome_unknown` with reason `may_have_reached_urssaf`; a duplicate rejection
  (`RejectionKind::DuplicateDeclaration`) → `already_declared`; another 400 → `rejected` (a generic `urssaf.rejected` error
  when URSSAF gave no code); `UrssafAuthenticationFailed` → `invalid_credentials`; a `RetryableException` that provably did
  not reach URSSAF (429, token failure, local rate limit) → `error`, then `pending` again while attempts remain
  (`RetryableUrssafFailure` is thrown after the commit, with the `Retry-After` delay as `retryAfterSeconds()`) or
  `ItemOutcome::Exhausted` returned with the record left in `error`; any other package exception → `error` / `pending` and
  rethrown unchanged; any other throwable (a bug, a foreign gateway) → `outcome_unknown` and rethrown (it may have been
  sent). A 200 is recorded `declared` on every state but `declared`; an accepted answer that cannot be recorded is a
  `DeclarationRecordingFailed`, never a resend. Any other answer on a record the claim no longer owns changes nothing.
- **Stored errors:** `SubmissionError::kind` is the `RejectionKind` value of the rejection (`technical` for an unavailable
  service or an unknown outcome, `unknown` for an unexpected response or failure). The synthetic codes are
  `urssaf.unavailable`, `urssaf.unexpected_response`, `urssaf.unexpected_failure` and `urssaf.rejected`; their message is the
  class name of the exception (never its message: a foreign exception may carry host data, ADR 0007).
- **Stale submissions:** `ReconcileStaleDeclarations` stores no error on the record it moves to `outcome_unknown` (reason
  `stale_submission` only) and reports it as `manual_review`. It can reconcile a record whose call is still in flight and
  then accepted: the late acceptance records the answer (ADR 0006), which is why `workflow.stale_submission_after` must
  exceed the HTTP client timeout.
- **Outbox relay:** `RelayOutbox` marks a failed delivery with the class and the code of the sink exception only
  (`Outbox::markFailed()` documents it), never its message (ADR 0007).
