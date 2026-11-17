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

## Amendment (2026-11-17, lead-accepted, lot 1)

- **Not masked:** amounts, rates, hours and day counts (salaries, `txPas`, declaration periods, care days) and the
  request aggregates that hold them (`DeclarationCommon`, the declarations, `WithholdingTax`, the estimate requests):
  they are business figures without an identifier, and a debug dump of them is not a re-identification risk on its own.
  The exception is `ChildminderChild`, which carries `joursAccueilHandicap` (a child's disability, health data) and is
  masked.
- Every `CalendarDate` parameter that holds a birth date (employer, employee, child) or a licence date is
  `#[SensitiveParameter]`, in the factories and the private constructors (a `CalendarDate` is not masked on its own);
  tests assert it by reflection.

## Amendment (2026-11-17, lead-accepted, lot 4a)

- `Mandate` keeps the full `EmployerReference` (031 needs the birth date even after the account was edited): the mandate
  birth date column uses the same encryptable DBAL type as the employer one (ADR 0014).
- URSSAF error **messages** are masked like descriptions (templates such as `[PERIODE1_MONO]` are filled with host
  values). Outbox failures store the exception class and code only, never the raw message of a host sink exception.
