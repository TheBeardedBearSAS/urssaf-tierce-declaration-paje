# Testing

How the package itself is tested (suites, fakes, quality gates), and the live sandbox contract suite. To test a host
application with the in-memory platform, see [standalone.md](standalone.md) and [symfony.md](symfony.md#simulator-in-tests).
Every command runs in Docker (`make` targets); no local PHP is needed.

## Suites

| Suite | Directory | Command | What it covers |
|---|---|---|---|
| `unit` | `tests/Unit` | `make test-unit` | Domain value objects and rules, wire mappers against `tests/Fixtures/wire/*.json` (requests serialised, responses parsed, error payloads in both shapes), gateway, handlers, simulator, lifecycles. |
| `functional` | `tests/Functional` | `make test-functional` | Symfony bundle wiring and commands, Doctrine mappings and repositories, standalone builder, README quick start. |
| `contract` | `tests/Contract` | `make test-contract` | Live URSSAF sandbox tests, plus the offline checks of their own support code. Never part of `make test`. |

`make test` runs `unit` and `functional` (the Makefile lists the directories that exist). `phpunit.xml.dist` defines the
`contract` suite separately, and no Make target, CI job or coverage run uses it except `make test-contract` and the manual
`contract` job.

## Fakes, not mocks

Tests use hand-written fakes and recorders (`tests/Unit/Application/Fake`: `FakeEmployerGateway`, `FakeSalaryGateway`,
`FixedClock`, `SequentialIdGenerator`, `Recording*`, `Journaling*`, `Racing*` for the concurrency cases) and the
`Testing\SimulatedPajeGateway` for scenarios across operations. An HTTP gateway test drives the real gateway through
`php-http/mock-client`. All values are fictitious.

## Contract traits (repositories)

The repository behaviour is written once as traits in `tests/Functional/Doctrine/Contract`
(`EmployerAccountRepositoryContract`, `MandateRepositoryContract`, `DeclarationRecordRepositoryContract`,
`OutboxContract`, `WorkflowStatisticsContract`) and run against the in-memory implementations
(`InMemory*ContractTest`) and against the Doctrine ones. A new repository implementation passes when it runs the same
contract (their test methods come from the trait).

## Doctrine and Symfony functional tests

- Doctrine (`tests/Functional/Doctrine`): XML mapping, DBAL types, repositories with optimistic locking, the atomic claim,
  the outbox, transactions and employer erasure, on SQLite (a file database for the concurrency cases).
- Symfony (`tests/Functional/Symfony`): the bundle in a kernel (configuration rules, services, console commands,
  Messenger handlers, locks, scheduler, validation, translations, diagnostics).

## Benchmarks

`make bench` (PHPBench in `tools/phpbench`, out of the package dependencies; `tests/Benchmark`: validation, wire
encoding, wire decoding). CI runs them on pull requests against the base commit and fails on a regression above 20 %.

## Coverage and mutation gates

- `make coverage`: 100 % of lines, methods and classes over `src` (unit + functional suites, `bin/check-coverage`).
- `make coverage-branches`: at least 96 % of the branches over `src` (Xdebug path coverage, slow, `bin/check-branch-coverage`;
  measured 96.1 %). The CI runs it on `main`, nightly and on demand, not on pull requests. Xdebug records no branch for a
  function invoked through a first-class callable (`array_map($this->check(...), $items)`, `$this->parse(...)` passed as a
  callback); the line and mutation gates see it, this one does not. Where a plain loop reads as well, prefer it to a
  first-class callable in `array_map`, so that the branches of the callee are measured.
- `make infection`: 100 % MSI and covered MSI over `src`, with its own initial test pass (`unit` + `functional`).
- `tests/` is not measured: the contract suite and its support code are outside both gates, and no threshold is lowered
  for them. The support code is nevertheless tested by `SandboxSupportTest` (below).
- `make ci` runs the whole gate (style, Rector, PHPStan, Mago, Deptrac, Composer checks, Hadolint, guard, coverage,
  mutation). Neither `make ci` nor any other Make target reaches the sandbox.

## Contract suite (live sandbox)

`make test-contract` runs `phpunit --testsuite=contract` in Docker against the URSSAF sandbox
(`https://api-edi.urssaf.fr`, the production host `api.urssaf.fr` is refused with a `SandboxMisconfigured`). Without
credentials every live test is skipped (`markTestSkipped`) and only the offline support tests run: the target passes.
The environment is forwarded variable by variable (`docker compose run -e NAME`), nothing is read from a file.

| Variable | Kind | Needed for |
|---|---|---|
| `URSSAF_SANDBOX_CLIENT_ID`, `URSSAF_SANDBOX_CLIENT_SECRET` | secrets | every live test (skipped when one is missing) |
| `URSSAF_SANDBOX_BASE_URI` | optional | another sandbox host than `https://api-edi.urssaf.fr` (never `api.urssaf.fr`) |
| `URSSAF_SANDBOX_TOKEN_URI` | optional | when set, every SDK token request goes there; the token test also probes the derived URI first; the production host is refused |
| `URSSAF_SANDBOX_EMPLOYER_NUMBER`, `URSSAF_SANDBOX_EMPLOYER_BIRTH_DATE` | test data | 020, 022, 023, 030, 031, 045, 046, 050, 051 (both or none) |
| `URSSAF_SANDBOX_EMPLOYEE_NUMBER` | test data | 021, 023, 045, 046, 050, 051 |
| `URSSAF_SANDBOX_CHILD_LAST_NAME`, `_FIRST_NAME`, `_BIRTH_DATE` | test data | 022, 045, 046, 050, 051 (all three or none; names upper case without accents) |
| `URSSAF_SANDBOX_ALLOW_WRITES` | opt-in | `1` runs the writing tests; any other value, or none, skips them |

A blank value counts as unset (CI passes an empty string for an unset repository variable). A wrong value is a failure
that names the variable and never prints the value. Put real sandbox values in your shell, a password manager or the
environment (or repository) variables, never in the repository.

| Class | Groups | Operations |
|---|---|---|
| `ReadOnlySandboxContractTest` | `contract` | token, 020, 021, 022, 040, 041 (nothing is stored) |
| `WriteSandboxContractTest` | `contract`, `contract-writes` | 030, 031, 023, 045, 046, 050, 051 (needs `URSSAF_SANDBOX_ALLOW_WRITES=1`) |
| `SandboxSupportTest` | `contract-support` | offline: variables, wiring, redaction, data builders |

`make test-contract` runs everything; `phpunit --testsuite=contract --group contract-writes` runs the writes only.
The writing tests register and cancel the mandate of the test employer (and register it again at the end), create
fictitious employees with 023 (random upper-case letters, the sandbox cannot delete them) and record one declaration per
activity (050, 051) after an accepted pre-declaration. A declaration is never resent; a rerun in the same month is
refused as a duplicate, which is recorded as an observation. Tests run in a random order, so each one is
self-contained and records the state it finds.

### Report

The run writes `var/contract/sandbox-report.json`: for each goal, `not_run`, `not_observed` or `observed` and a value made of
statuses, error codes, booleans, counts and JSON types. It never holds a response body, a message or description, a
Pajemploi number, a NIR, a name, a birth date, a reference, a token or a query string (the exchange capture keeps the
path with the 14-character numbers templated, the status, header names and the shape of the body only). The goals, from
[api-reference.md](api-reference.md) section 5:

| Point | Question |
|---|---|
| `mandate-prerequisite` | Which operations answer `MANDAT_INEXISTANT` when the employer has no mandate (020, 022, 023, 045, 046)? |
| `estimate-context-free` | Do 040 and 041 really need no mandate? |
| `error-codes` | Spelling of the `ER_API_DECLA_*` codes (4 or 5 digits), and the codes missing from `ApiErrorCode`. |
| `023-required-fields` | Which fields a creation really requires (identity only, without address, without activity type, complete). |
| `retry-safety-023`, `-030`, `-031` | What a second identical call answers (the SDK never retries these writes by default). |
| `response-decimals` | Decimals of the response amounts (`mnt*`) and of `txPas`, per operation. |
| `online-account-state`, `children-022`, `unknown-employer-020`, `unknown-employee-021` | Wire spelling of `etatCompteEnLigne`, child statuses, answers for unknown employer or employee. |
| `predeclaration`, `declaration` | Outcome, non-blocking codes and shape of 045, 046, 050, 051. |
| `error-body-shape`, `headers`, `quota-429`, `token-host` | List or object error bodies, response headers, quota answer, token endpoint. |

CI: Actions > CI > Run workflow > `contract` (and `contract_writes` to write). The job runs in the `urssaf-sandbox`
environment (secrets for the client id and secret, environment (or repository) variables for the others) and uploads the report as the
`contract-report` artifact. Restrict the `urssaf-sandbox` environment to the `main` branch (Settings > Environments >
Deployment branches and tags) so that no other branch can read the credentials; required reviewers are optional. When a run settles a point, update `api-reference.md` section 5 and remove it from the
open points.
