# ADR 0014 — Doctrine bridge

Status: Accepted (2026-11-17). Relates to [ADR 0000](0000-reference-architecture.md),
[ADR 0006](0006-local-lifecycles.md), [ADR 0007](0007-personal-data.md), [ADR 0012](0012-integration-events.md),
[ADR 0013](0013-symfony-bridge.md).

## Context

The roadmap asks for a Doctrine bridge persisting employers, mandates and declarations. The reference ships one (ORM 3
/ DBAL 4, XML mapping, custom DBAL types). PAJE has no pairing and no status polling (no progress table), a "one active
mandate per employer" rule, a record claimed by an atomic `UPDATE`, and minimised personal data.

## Decision

1. **Reference structure.** XML mapping in `Bridge/Doctrine/Resources/mapping`, DBAL types `urssaf_paje_*`, tables
   prefixed `urssaf_paje_`, dedicated entity manager recommended, no migration shipped, `DoctrineTransactionManager`,
   `DoctrineOutbox` (DBAL), `DoctrineWorkflowStatistics`. No name collides with another URSSAF package.
2. **No progress table.** Every filtered or sorted column is a real column of the aggregate table.
3. **Active mandate key table.** `urssaf_paje_active_mandates` (primary key tenant + employer number), written with DBAL
   in the mandate save transaction (a partial unique index is not portable to MySQL).
4. **Optimistic concurrency.** Native Doctrine `version` field on the three aggregates (persistence token, not a domain
   concept); `OptimisticLockException` → `InvariantViolation` `<aggregate>.concurrent_modification`. The claim
   `UPDATE` bumps the version.
5. **JSON columns** for nullable groups (last employer verification, declaration result, submission errors,
   non-blocking messages): Doctrine cannot hydrate a nullable embeddable and no query filters on them.
6. **Dedicated birth date type** `urssaf_paje_birth_date` for the employer birth date (encryption recipe, ADR 0007).
7. **Masked conversion errors** on columns that may hold personal data.
8. **Portability.** DQL and the DBAL query builder only; deterministic tie-breakers; CI runs on SQLite.
9. **Standalone.** `withDoctrine(EntityManagerInterface)` sets the repositories, transaction manager and statistics.

## Consequences

- Migrations are the host's (`doctrine:migrations:diff`); the schema diagnostic reports pending changes.
- Documented in `docs/doctrine.md`.

## Amendment (2026-11-17, lead-accepted, lot 4b)

- Decision 4 is now a contract of the ports, not only of this bridge (ADR 0006, same date): the in-memory repositories refuse
  a stale save with the same `<aggregate>.concurrent_modification` codes, and the shared repository contract traits
  (`tests/Functional/Doctrine/Contract`) hold the scenarios (a copy saved after another writer saved it, a copy read before a
  claim, a copy found by a query, a copy erased by another writer), which this bridge must satisfy. Each trait declares an
  abstract `anotherWriter()` seam returning an independent repository over the same store (the in-memory test returns the
  repository itself; the bridge test returns a repository over a second EntityManager), and the competing copies are read
  and saved through it. The save of an instance that was never read for an identifier that is already stored is not part
  of the contract: each adapter reports it in its own way, and the bridge decides its behaviour when it is built.

## Amendment (2026-11-17, lead-accepted, lot 6)

- **Types.** Fourteen DBAL types, all named `urssaf_paje_*` (`DoctrineIntegration::types()`): `tenant_id`, `external_reference`,
  `employer_account_id`, `mandate_id`, `declaration_record_id`, `employer_number`, `employee_number`, `declaration_fingerprint`,
  `calendar_date`, `birth_date`, `instant` (UTC, second precision), `employer_verification`, `submission_errors` and
  `declaration_result` (the last three are JSON). There is no declaration id type: PAJE has no declaration identifier, the
  reference of an accepted declaration lives in the result document.
- **Masked conversion errors (decision 7).** The employer number, the employee number and the birth date are masked in every
  conversion error, in both directions (a stored value the domain refuses, a stored value of the wrong type, a foreign PHP
  value); so are the three JSON types (a stored document, a foreign PHP value). The other types quote their value.
- **Schema.** `urssaf_paje_employer_accounts` (unique `(tenant_id, employer_number)` and `(tenant_id, external_reference)`),
  `urssaf_paje_mandates` (`employer_account_id`, `origin`, `state`, timeline, `birth_date`; the external reference is not unique),
  `urssaf_paje_declaration_records` (unique `(tenant_id, external_reference)` only, no `declaration_id`),
  `urssaf_paje_active_mandates` (primary key `(tenant_id, employer_number)`, unique `mandate_id`) and `urssaf_paje_outbox`. The
  employer identification (`employer_number`, `birth_date`) is one embeddable shared by the account and the mandate, and the
  declaration period one embeddable of the snapshot (`period_start`, `period_end`, `payment_date`), so the columns are the same
  everywhere. Indexes serve the port queries (last checked, stage and state counts, mandates of an employer by registration,
  records by state and update, stale submissions) and the erasure of ADR 0015: `(tenant_id, employer_number, state)` on the
  records for `countByEmployer` and `deleteByEmployer`.
- **Documents.** The result document holds the document reference, the amounts (cents, rate as its decimal string, dates as
  `Y-m-d`), the Pajemploi+ flag, the transfer date and the non-blocking messages. The **child checks are never written** (ADR 0007:
  they hold child names and birth dates): a result is read back with none, as the aggregate keeps it. The last employer verification
  keeps the raw online account state URSSAF sent.
- **Tests.** `tests/Functional/Doctrine/Mapping` holds the schema test (exact columns, nullability, indexes and unique constraints
  per table on SQLite), the ADR 0007 test (no column named after personal data, `birth_date` only in accounts and mandates, no
  personal value in any row) and the mapping round trips; the shared support is `tests/Functional/Doctrine/Support/TestDatabase`.

## Amendment (2026-11-17, lead-accepted, lot 6, Symfony and Standalone wiring)

- **Symfony.** The `doctrine` driver, the `persistence.entity_manager` option, the prepended mapping, the build failure without
  DoctrineBundle and `DatabaseSchemaCheck` are recorded in ADR 0013 (amendment lot 6). The test application of the functional suite can
  boot DoctrineBundle on an in-memory SQLite.
- **Standalone.** `UrssafTierceDeclarationPajeBuilder::withDoctrine(EntityManagerInterface)` sets the three repositories, the
  `DoctrineTransactionManager` and the `DoctrineWorkflowStatistics`; the outbox stays separate
  (`withOutbox(new DoctrineOutbox($entityManager), $sink)`) so that a host can choose another outbox. The builder does not register the types
  nor the mapping: `DoctrineIntegration::registerTypes()` and `mappingDriver()` are the host's to call (`docs/doctrine.md`).
- **Documentation.** `docs/doctrine.md` covers the setup, the dedicated entity manager, the schema and migrations left to the host, the
  encryption recipe of the birth-date type and the JSON columns, concurrency, erasure and backups.

## Amendment (2026-11-17, lead-accepted, lot 6, repositories)

- **Save protocol (decision 4, amendment of lot 4b).** The three repositories share `AggregateStore`. A save is refused with
  `<aggregate>.concurrent_modification` (no parameters, like the in-memory repositories) **before anything is flushed** when the
  stored version is not the instance's (another writer saved, claimed or erased it), and the refused instance is detached, so the
  next read returns what is stored and the entity manager stays open. The save of an instance that is not managed (never read,
  or detached by `clear()`) while its identifier is stored is refused the same way, never an overwrite: this is the behaviour the
  lot 4b amendment left to the bridge. The native `version` column stays the backstop for a writer that wins the race between
  the check and the flush (the ORM then closes the entity manager, as after any failed flush, and the save is refused with the
  same code); the identifier of an instance created concurrently, and a unique constraint broken by a concurrent writer, are
  reported the same way or as the domain duplicate violation. Concurrency is checked first, then uniqueness, as in memory.
- **Reads.** `get()` replaces a managed copy that another writer changed or erased by what is stored; the queries return the
  instance the entity manager already manages for a row, like every Doctrine query.
- **Active mandate.** The key row is rewritten in the transaction of the save (delete, then insert if the mandate is not
  cancelled); the primary key violation is reported as `mandate.duplicate_active`; the employer number is never in a violation
  parameter (ADR 0007). `delete()` removes the mandate and its key row in one transaction.
- **Claim and erasure.** `claimForSubmission()` is one conditional `UPDATE ... WHERE state = pending` that bumps the version and
  detaches the managed copy. `deleteByEmployer()` selects the ids (creation, then id), deletes under the same condition (tenant, employer, states: a record claimed in between survives, so the
  re-count of ADR 0015 catches it) and detaches the managed copies; `countByEmployer()` and `deleteByEmployer()` use the index `(tenant_id, employer_number, state)`.
- **Order.** Every list ends with the identifier (accounts: never checked first, then last check, creation, id; mandates:
  registration, id; records: update, creation, id; stale submissions: last attempt, creation, id); the statistics are ordered
  by tenant, then state.
- **Transactions.** `DoctrineTransactionManager` rolls back and detaches the entities of this package (never the host's) when
  the operation throws, and does not close the entity manager.
