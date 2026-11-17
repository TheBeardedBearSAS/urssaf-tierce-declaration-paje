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
