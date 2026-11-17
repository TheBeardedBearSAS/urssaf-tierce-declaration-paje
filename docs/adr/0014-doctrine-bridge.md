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
