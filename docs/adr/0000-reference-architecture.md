# ADR 0000 — Reference architecture inherited from urssaf-tiers-prestations

Status: Accepted (2026-11-17). Reference: `thebeardedbear/urssaf-tiers-prestations` (HEAD), README "Architecture",
docs/api-reference.md §4, docs/workflow.md, docs/symfony.md, docs/doctrine.md, docs/testing.md, docs/standalone.md,
docs/integration-events.md, phpstan.neon.dist, mago.toml, rector.php. Secondary reference:
`thebeardedbear/urssaf-tierce-declaration-cesu` (sibling "Tierce Déclaration" API sharing `/tiersdecl/v1`, the OAuth2
flow and the mandate model); its adaptations are reused only through the PAJE ADRs listed below.

## Context

Every URSSAF package shares one architecture so that integrators and maintainers move between them freely. This ADR
lists what this package inherits, adapts or leaves out. Any further decision or deviation needs its own ADR
`docs/adr/NNNN-<title>.md` (Context / Decision / Consequences), accepted by the lead before the code implementing it.

## Inherited as is

1. **Layers (Deptrac).** Domain (no dependency), Contract (no dependency); Application → Domain, Contract;
   Infrastructure → Application, Domain, Contract, Psr; Testing → Domain, Contract, Application, Infrastructure;
   Standalone → Domain, Contract, Application, Infrastructure, DoctrineBridge, Doctrine, Psr; DoctrineBridge → Domain,
   Contract, Application, Infrastructure, Doctrine, Psr; SymfonyBridge → all of the above + Testing, Symfony. Tests are
   not analysed.
2. **PSR-only runtime.** `php >= 8.2`, the `ext-*` the code needs (`ext-json`, `ext-mbstring`; the reference also
   has `ext-ctype` / `ext-filter`, added only if used) and `psr/*` interfaces, added with the code that uses them.
   Symfony, Doctrine and HTTP client implementations stay in `require-dev` + `suggest`, with the reference `conflict`
   floors (`symfony/scheduler <6.4.3` is added with the Scheduler code).
3. **Value objects.** `final readonly`, private constructor, named constructors (`of`, `fromString`, `fromCents`…),
   stable violation codes `<subject>.<reason>`, no raw value in exception messages; personal data masked
   (`__debugInfo`, `#[SensitiveParameter]`); `Credentials` not serializable. No `float` in the domain.
4. **Validation.** Notification pattern (`Violation`, `ValidationResult`, `Validator`); factories collect every
   violation of a draft at once (`ValidationFailed`).
5. **Errors.** `UrssafException` and `RetryableException` markers; abstract `DomainException` (`violationCode`,
   `params`, `toViolation`) with `InvalidValue`, `InvariantViolation`, `InvalidStateTransition`, `RecordNotFound`;
   gateway channels `UrssafRequestRejected` (400), `UrssafAuthenticationFailed` (401/403/512),
   `UrssafUnavailable` (429/5xx/transport, `mayHaveReachedUrssaf()`), `UnexpectedUrssafResponse`. Wire codes are
   classified in Infrastructure and exposed as port enums: the Application never compares wire strings.
6. **Wire.** `Payload` is the single reader of untyped data; `JsonEncoder` refuses floats (`JsonDecimal` literals);
   money in integer cents; unknown codes kept raw next to a nullable enum; `{}` forced for empty objects.
7. **HTTP.** OAuth2 client credentials, token cached in a `TokenStore` (key = hash of environment, base URI and client
   id) with a safety margin; one replay after a 401; `RetryAfter` (delta-seconds only); sandbox host overridable;
   no secret, token, body or query string logged.
8. **Application.** One `final readonly` handler per use case (`__invoke`); ports implemented by the outer layers;
   `UnitOfWork` writing integration events to the outbox in the state-change transaction and publishing domain
   events after commit (failures isolated through `FailureReporter`); atomic claim before any URSSAF write; attempt
   counter; `invalid_credentials` state with requeue; every tunable in `WorkflowPolicy`.
9. **Events.** Domain events recorded by aggregates; public integration events in the `Contract` layer (scalar,
   versioned `TYPE` / `VERSION`) written to an outbox and relayed at least once.
10. **Multi-tenant.** `TenantId` on every aggregate and row; `CredentialsProvider::forTenant()`; unique constraints,
    locks and rate limiting per tenant.
11. **Bridges.** Symfony: `AbstractBundle`, `ServiceIds` constants, `BundleConfiguration` with build-time rules,
    console commands, scalar Messenger messages with recoverable / unrecoverable mapping, non-blocking Lock,
    Scheduler, diagnostics, translations en / fr, Flex recipe. Doctrine: XML mapping, custom DBAL types, dedicated
    entity manager recommended, no migration shipped.
12. **Tests.** Unit / Functional / Contract suites; hand-written fakes rather than mocks; repository contract traits
    run against the in-memory and Doctrine implementations; Contract suite skipped without
    `URSSAF_SANDBOX_CLIENT_ID` / `URSSAF_SANDBOX_CLIENT_SECRET` and run only by a manual CI job; PHPBench benchmarks
    (`tests/Benchmark/*Bench.php`, CI fails above a 20 % regression); 100 % lines / methods / classes, Infection MSI
    100 %, PHPStan max, Mago, Rector, PHP-CS-Fixer, Deptrac, composer checks, hadolint, guard.
13. **Quality exceptions.** Only those justified in the reference configuration: container parameters,
    `noExtends` for framework extension points / `IntegrationEvent` / `DomainException`, default and nullable
    parameters for value objects, nullable returns for finders, non-final Doctrine entities, static assertions in
    tests, wire DTO constructor threshold 12, no `mago fmt`.

## Adapted (each with its own ADR)

| Topic | ADR |
|---|---|
| Date-only `yyyy-MM-dd` encoding, business time zone, placeholders rejected | [0001](0001-date-only-encoding.md) |
| HTTP: DELETE, POST with query and body, `{}` bodies, empty 200, no contract header, 512 | [0002](0002-http-client-adaptations.md) |
| No reconciliation lookup: 050 / 051 never resent, `outcome_unknown` | [0003](0003-retry-and-unknown-outcome.md) |
| Sum types (employee identification, AMA / GED declarations, contract ends) | [0004](0004-sum-types.md) |
| Error classification by code (array or object payload, trimmed codes) | [0005](0005-error-classification.md) |
| Local lifecycles of employers, mandates and declarations instead of pairing / status polling | [0006](0006-local-lifecycles.md) |
| Personal data | [0007](0007-personal-data.md) |
| Results: amounts, rates and non-blocking messages; pre-declaration check | [0008](0008-results-and-predeclaration-check.md) |
| Local guards and opt-outs (mandate prerequisite unknown) | [0009](0009-guards-and-opt-outs.md) |
| Tenant model | [0010](0010-tenant-model.md) |
| Unpublished quotas | [0011](0011-quotas-and-rate-limit.md) |
| Integration event catalogue `urssaf.paje.*` | [0012](0012-integration-events.md) |
| Symfony bridge | [0013](0013-symfony-bridge.md) |
| Doctrine bridge | [0014](0014-doctrine-bridge.md) |
| Employer erasure (GDPR) | [0015](0015-employer-erasure.md) |

Smaller adaptations, accepted here:

- Wire mappers are split per direction (`…RequestMapper` / `…ResponseMapper`) instead of one class per operation: the
  12 operations share request fragments (`InputDeclCommun`, `InputSp`, `InputEnfant`) and response fragments
  (`DeclarationCommun`, `Erreur`). Taken from the CESU package.
- Test repositories (`Journaling*`) decorate the production in-memory repositories instead of duplicating them; the
  shared contract traits prove the inner ones. Taken from the CESU package.
- `ApiConfiguration` keeps the reference public constructor with defaulted parameters plus `forEnvironment()`, and
  carries a business time zone instead of the pairing time zone, and optional request-id / application-id header names
  (off by default, ADR 0002).
- PHPStan analyses against PHP 8.2 (`phpVersion: 80200`) and `type_coverage.constant` is 0: typed class constants need
  PHP 8.3, so the reference setting (inert on PHP 8.2) fails `make ci PHP_VERSION=8.4`. Taken from the CESU package.

## Not applicable

- Batching by 10 (methods 050 / 070 of Tiers de prestations): every PAJE call is unitary.
- ASCII transliteration of names: the contract patterns (`[A-Z'\-\s]*`) are validated as given; the package never
  rewrites a name (api-reference §4.11). `villeNaissance` accepts the accented PDF set.
- URSSAF status polling with polling windows (settled / unknown / max age): PAJE has no status endpoint; only the
  employer verification (020) is re-read.
- Pairing acceptance instant as lower bound of employment periods; billing third-party id; progress table.
- `x-request-id` / `recouv.application.id` headers: not in the PAJE contract (optional configuration, ADR 0002).

## Process

New decisions and deviations get an ADR (Context / Decision / Consequences), accepted by the lead before the code that
implements them. An amendment is appended to the ADR it changes, dated and marked lead-accepted.

## Amendment (2026-11-17, lead-accepted, lot 1)

- `ergebnis.noParameterWithNullDefaultValue` is ignored, by path only, for the three employee identifications (optional
  query birth date) and for the domain test builders (`tests/Unit/Domain/*Test.php`, `*Samples.php`), so that each test
  names only what it varies. The reference has no such ignore; production code outside the three files is not
  concerned (justified in `phpstan.neon.dist`).

## Amendment (2026-11-17, lead-accepted, lot 4b)

- Item 4 is implemented by factories in `Domain\{Employer,Employee,Child,Declaration,Estimate}\Input`, one per aggregate
  family, with the same three entry points: `validate(array)` returns every violation (warnings included),
  `fromInput(array)` returns the aggregate or throws `ValidationFailed` with all of them. There is no draft class: the raw
  input is an `array` as a host, a JSON body or the console gives it.
- The input keys and the violation paths are the same English snake_case names (`common.hours`,
  `children[0].last_name`, `contract_end.notice_indemnity`); the camelCase paths of the aggregates are translated to them
  (`netMonthlySalary` becomes `net_monthly_salary`, `consecutive24hDays` becomes `consecutive_24h_days`). The shared
  reader is `Validation\RawFields` (internal): text is a string or an integer, an integer is an integer or a digit
  string, a boolean is a boolean or "1" / "0" / "true" / "false", a decimal is text (a float is refused); any other type
  reports `<key>.invalid_type`, never a `TypeError`. A null or empty value is missing.
- The rules stay in the value objects and aggregates; the factories only parse and convert their `InvalidValue` and
  `ValidationFailed` into violations. To report every violation at once, an aggregate is built even when a required
  part is missing or invalid, with a valid stand-in (`DeclarationPlaceholders` and constants), so that a missing
  salary does not hide an out-of-range hours count. The object built with a stand-in is never returned.
- The employee criteria follow the priority of api-reference §2.2 (ADR 0004): the Pajemploi number, then the NIR, then
  the administrative data. The highest-priority criterion given is kept, every given criterion is validated, and
  "employee_identification.empty" is reported when none is given (path `identity` for an identification, empty path,
  so `employee`, for a declaration, which accepts the number and the NIR only). An exclusivity violation would refuse a
  request the contract accepts (ADR 0009).
- The activity of a declaration or an estimate is the entry point (`childminder()` / `homeCare()`, or
  `fromInput(DeclarationActivity, array)`), never a field of the input.
- `ChildListFactory` (operation 022) enforces no minimum: the contract publishes none. The deprecated
  `finDeContrat.enfants` of a childminder is not accepted by `ChildminderContractEndFactory`. The non-empty
  `EmployeeCreation::identity()` rule of an association by administrative data (ADR 0009 amendment) is the business of the
  association use case, not of `EmployeeCreationFactory`.
- Undocumented codes (`isDocumented()` false) are accepted without warning: ADR 0009 refuses nothing outside the Swagger.
- Deviation: `phpstan.neon.dist` lists the test fakes that store a race-scenario closure and call it from inside the faked port
  in `shipmonkRules.forbidCheckedExceptionInCallable.allowedCheckedExceptionCallables` (test code only, never `src/`; the
  closure's exception is the scenario).

## Amendment (2026-11-18, lead-accepted)

- Infection `timeout` is 180 s instead of the reference 30 s, and `make infection` and the CI job fail when a mutant
  is *skipped*: Infection never runs a mutant whose covering tests exceed the limit in total, while MSI still reads
  100 %. The change makes the gate stricter (justified in `infection.json5`).
