# ADR 0015 — Employer erasure (GDPR)

Status: Accepted (2026-11-17). Relates to [ADR 0003](0003-retry-and-unknown-outcome.md),
[ADR 0006](0006-local-lifecycles.md), [ADR 0007](0007-personal-data.md), [ADR 0012](0012-integration-events.md),
[ADR 0014](0014-doctrine-bridge.md).

## Context

The package stores personal data (employer birth date, result amounts, URSSAF descriptions) and leaves retention to the
host (ADR 0007). Hosts need a supported way to erase an employer from the package store. The package does not decide
*when* data may be erased; it only protects its own workflow guarantees. Taken from the CESU package.

## Decision

1. `ForgetEmployer` erases, in one `UnitOfWork` transaction, the employer account, its mandates and its declaration
   records. Nothing is sent to URSSAF: cancelling the mandate (031) is the host's separate choice, made before.
2. Refusals (`EmployerErasureRefused`), checked before any deletion, in this order: a non-cancelled mandate
   (`employer_account.forget_active_mandate`, opt-in `allowActiveMandate`); a record `submitting` (always refused);
   a record `outcome_unknown` (`employer_account.forget_unknown_outcome`, opt-in `allowUnknownOutcomes`). The opt-in
   defaults come from `WorkflowPolicy` (both `false`); the command fields are nullable (`null` = policy).
3. Ports: `MandateRepository::delete()`, `DeclarationRecordRepository::countByEmployer()` and `deleteByEmployer()`;
   counts and ids come from selected ids, never from affected-row counts.
4. Event `urssaf.paje.employer.forgotten` (identifiers only); outbox entries are not purged.
5. Console command `urssaf:paje:employer:forget` with confirmation unless `--force`; `--force` never overrides a
   safety default.

## Consequences

- Idempotency of `DeclareSalary` is lost for the erased external references.
- URSSAF keeps its data and the mandate.

## Amendment (2026-11-17, lead-accepted, lot 4a)

- `EmployerAccount::forget(Instant, list<MandateId>, list<DeclarationRecordId>)` records `EmployerForgotten` (account id,
  tenant, external reference, erased ids, instant: no employer number, no birth date) and changes no state; the caller
  deletes the account, its mandates and its records in the same transaction, then releases the event.
- `MandateRepository::delete(MandateId)` is a no-op for an unknown id and records no event.

## Amendment (2026-11-17, lead-accepted, lot 4b)

- `ForgetEmployer` (`EmployerSelector`, nullable `allowActiveMandate` / `allowUnknownOutcomes`) returns an
  `EmployerErasure` (account id, mandate ids, declaration record ids). `EmployerErasureRefused` carries the account id and a
  count only (`employer_account.forget_active_mandate`, `forget_submission_in_flight`, `forget_unknown_outcome`). After the
  deletions the handler re-counts the employer's records and mandates and, if any is left (another process wrote one),
  throws `InvariantViolation` `employer_account.concurrent_modification`: the transaction is rolled back and no event is
  published.
- A 050 / 051 accepted after `ForgetEmployer` erased its record cannot be recorded: `DeclareSalary` throws
  `DeclarationRecordingFailed` carrying the `referenceDocumentaire` (ADR 0006, settlement table); any other answer of that call
  changes nothing.
