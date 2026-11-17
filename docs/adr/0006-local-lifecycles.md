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
