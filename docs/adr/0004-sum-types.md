# ADR 0004 — Sum types

Status: Accepted (2026-11-17). Relates to [ADR 0000](0000-reference-architecture.md).

## Context

api-reference §4 decisions 1 and 10 model closed alternatives: an employee is identified by Pajemploi number, by NIR,
or by administrative data (priority 1 / 2 / 3); a declaration is either a childminder (AMA) or a home childcare (GED)
declaration, with different required fields and different contract-end indemnities. PHPStan `ergebnis.noExtends`
forbids class inheritance outside justified extension points. The reference has no sum type of this kind; the CESU
package uses interfaces with final implementations.

## Decision

- `Domain\Employee\EmployeeIdentification` is an interface implemented by final readonly `EmployeeByNumber`,
  `EmployeeByNir` and `EmployeeByAdministrativeData`; each may carry the optional employee birth date of the query
  (`dtNaissanceSalarie`). The declarations' `InputSp` uses the first two only (the contract has no administrative data
  there): `DeclaredEmployee` is an interface implemented by `EmployeeByNumber` and `EmployeeByNir`.
- `Domain\Declaration\Declaration` is an interface implemented by final readonly `ChildminderDeclaration` (AMA) and
  `HomeCareDeclaration` (GED). Shared fields live in a final readonly `DeclarationCommon` (`InputDeclCommun`) composed
  by both.
- `ChildminderContractEnd` and `HomeCareContractEnd` are two final classes (no common interface: they never travel
  alone).
- `Domain\Estimate\EstimateRequest` is an interface implemented by `ChildminderEstimateRequest` and
  `HomeCareEstimateRequest`.
- Consumers branch with `match (true) { $x instanceof A => …, }` and a final `default` throwing `InvariantViolation`
  (kept for exhaustiveness, covered by a test double in tests only).

## Consequences

- No `extends` in the domain; adding a variant means updating every `match` (caught by tests and Infection).
- The gateway port keeps one method per operation (§4 decision 1); the Application chooses the method by variant.
