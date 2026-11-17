# ADR 0007 — Personal data

Status: Accepted (2026-11-17). Relates to [ADR 0000](0000-reference-architecture.md).

## Context

The package handles NIR, employer and employee Pajemploi numbers, birth dates, employee identity and address, child
names and birth dates, salaries and withholding rates (api-reference §4 decisions 2 and 12: "never logged, never put in
exception messages"). The reference documents encryption at rest as a recipe (DBAL type override).

## Decision

- **Never persisted:** the NIR, employee names, birth data and address, child names and birth dates. A declaration
  record keeps the identification kind (`number` / `nir`) and, when the host used it, the employee Pajemploi number.
- **Persisted:** the employer Pajemploi number and birth date (every employer-scoped call needs both; the birth date
  has its own DBAL type so it can be encrypted alone), the declaration period, the fingerprint, the result amounts,
  `txPas`, `referenceDocumentaire`, and URSSAF error codes and descriptions (descriptions may echo host values: they
  are personal data, retention is the host's policy, encryption recipe documented).
- **Masked:** every value object carrying such data masks itself in `__debugInfo`, has no `__toString`, and every
  parameter carrying it is `#[SensitiveParameter]`. Exception messages and logs carry codes, operation names, HTTP
  statuses and durations only — never a query string (it holds the NIR and birth dates, ADR 0002), a body or a
  description.
- **Events and outbox:** identifiers, states and codes only (ADR 0012).

## Consequences

- A declaration cannot be re-sent from storage alone (consistent with ADR 0003): the host keeps its payroll data.
- A Doctrine test asserts that no column exists for NIR, names, address or child data.
- Erasure: ADR 0015.
