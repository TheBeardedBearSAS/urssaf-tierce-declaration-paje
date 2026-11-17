# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed

- Every source file now carries a copyright and `SPDX-License-Identifier: MIT` header, enforced by PHP CS Fixer
  (`header_comment`) for PHP and by `make guard` (`bin/check-spdx`) for the other source files.

## [0.1.0] - 2026-11-17

First release.

### Upgrading

- Driver `doctrine`: generate and run the migration for the `urssaf_paje_*` tables (`doctrine:migrations:diff
  --em=urssaf_paje`, `doctrine:migrations:migrate`); the package ships no migration. `urssaf:paje:check` reports the
  missing tables.

### Added

- Sandbox contract suite (`make test-contract`, manual CI job): live tests of the 12 operations against the URSSAF
  sandbox, opt-in writes, a redacted report in `var/contract/sandbox-report.json` that settles the open points of
  `docs/api-reference.md` section 5 (mandate prerequisite, `ER_API_DECLA_*` spelling, 023 required fields, retry safety,
  response decimals); `docs/testing.md`.
- Doctrine bridge (ORM 3 / DBAL 4, SQLite in CI): XML mapping, `urssaf_paje_*` tables and DBAL types, repositories with
  native optimistic locking (`*.concurrent_modification`), one-active-mandate key table, claim by atomic `UPDATE`, outbox,
  workflow statistics, transaction manager and employer erasure; the birth date has its own type (encryption recipe) and no
  NIR, employee or child name, nor address is stored. Symfony: `persistence.driver: doctrine` (default when `doctrine/orm`
  is installed, fails at build without DoctrineBundle), `persistence.entity_manager`, mapping prepended to DoctrineBundle,
  `database schema` diagnostic. Standalone: `withDoctrine()` (`docs/doctrine.md`).
- Symfony bundle `UrssafTierceDeclarationPajeBundle` (alias `urssaf_tierce_declaration_paje`, Symfony 6.4 / 7.x):
  configuration with build-time rules, per-tenant credentials from `%env()%`, console commands `urssaf:paje:*`
  (hidden prompts for birth dates and NIR, payloads from a JSON file, no URSSAF description printed), Messenger employer
  refresh, non-blocking locks, safety-cron schedule, diagnostics (`urssaf:paje:check`, `urssaf:paje:status`),
  English and French translations of every violation code, simulator refused in production (`docs/symfony.md`).
- Use cases: track / import employers, register / import / cancel / record mandates, verify employer, employee and
  children, associate an employee, estimate contributions, pre-declare and declare a salary (AMA / GED), reconcile and
  resolve unknown outcomes, requeue after invalid credentials, relay and purge the outbox, forget an employer, workflow
  overview. Declarations follow a settlement table (`docs/workflow.md`, ADR 0006): an accepted 050 / 051 answer is
  never discarded nor resent, repository saves are protected by optimistic concurrency, and only
  `MandateRecordingFailed` / `DeclarationRecordingFailed` signal an accepted call that could not be recorded.
- Draft and factory layer: raw host input validated in one pass with nested violation paths.
- `Testing\SimulatedPajeGateway` (in-memory platform for host tests, refused in production) and the framework-free
  `Standalone` builder (`docs/standalone.md`); README quick start.
- Local lifecycles: `EmployerAccount` (last 020 snapshot, stage), `Mandate` (registered / cancelled, registered or
  imported) and `DeclarationRecord` (pending → submitting → declared / rejected / already declared / outcome unknown /
  error / invalid credentials) with a personal-data-free fingerprint; repositories, domain events, `UnitOfWork` with an
  outbox written in the same transaction, `WorkflowPolicy`, in-memory implementations, and the public integration
  events `urssaf.paje.*` v1 (`docs/integration-events.md`).
- HTTP gateway: `UrssafHttpGateway` (the 12 operations behind the `PajeGateway` port and its sub-ports),
  `UrssafGatewayFactory`, `JsonApiClient` (bearer token, one replay after a 401, status mapping per operation class:
  050 / 051 are never resent and an uncertain outcome raises `OutcomeUnknown`, 429 is retryable with `Retry-After`),
  `OAuth2TokenProvider` (client credentials, scope `tiersdecl.paje`, cached in a PSR-6 / PSR-16 / in-memory token
  store), `ApiConfiguration` (environment, base URI override, optional correlation headers, retry-safe writes),
  optional sliding-window rate limiter (off by default: quotas are not published). Transport errors are sanitised and
  nothing personal is logged.
- Runtime dependencies: `ext-json`, `ext-mbstring` and the PSR interfaces (`psr/cache`, `psr/clock`,
  `psr/http-client`, `psr/http-factory`, `psr/http-message`, `psr/log`, `psr/simple-cache`).
- Wire layer (`src/Infrastructure/Urssaf/Wire`): request and response mappers of the 12 operations (query, path and
  body parameters, `{}` bodies, amounts as 2-decimal JSON literals, strict dates), error payload parsing (array or
  single object, trimmed codes), `ApiErrorCode` catalogue and classification into `RejectionKind`, non-blocking
  messages.
- Benchmarks (`tests/Benchmark`): validation, wire encoding and decoding of the official examples (`make bench`).
- Domain model (`src/Domain`): identifiers (Pajemploi employer and employee numbers, NIR), employer reference,
  employee identification sum type (by number, by NIR, by administrative data), address, children, AMA and GED
  declarations with their contract ends, estimate requests, results (amounts, withholding tax, child checks,
  non-blocking messages), signed `Amount` in cents, exact `Rate`, strict `CalendarDate`, validation by notification and
  domain exceptions; personal data masked (ADR 0007).
- Architecture decision records (`docs/adr/`): the reference architecture inherited from `urssaf-tiers-prestations`
  (ADR 0000) and the PAJE-specific decisions (ADR 0001 to 0015).
- Project skeleton: Docker tooling, quality gates (`make ci`) and GitHub Actions CI, identical to the other URSSAF
  packages.
- Specification: official OpenAPI contracts (`docs/openapi/`), official documentation (links in `docs/api-reference.md`, local copy in the git-ignored `docs/reference/`), technical
  reference (`docs/api-reference.md`) and implementation roadmap (`docs/roadmap.md`).
- Signed releases (workflow `release.yml`): each GitHub release carries the distribution archive, its `SHA256SUMS`
  and a SLSA build provenance attestation (`*.intoto.jsonl`, Sigstore); see *Verifying a release* in SECURITY.md.
- CodeQL analysis of the GitHub Actions workflows (workflow `codeql.yml`) on every pull request and push to `main`.

### Changed

- Deptrac: `Contract`, `Testing` and `Standalone` layers, as in the reference package. PHPStan analyses against PHP 8.2
  (`phpVersion: 80200`, typed-constant coverage disabled) so that `make ci` is identical on every supported version.
- CI: verifiable quality proofs (Codecov, Stryker Dashboard, SonarQube Cloud, OpenSSF Scorecard; active once the
  repository is public), every GitHub Action pinned to a commit SHA and kept up to date by Dependabot, hadolint on the
  Dockerfile (`make hadolint`, part of `make ci`) with a non-root development image, and benchmarks with PHPBench
  (`make bench`, CI job `benchmark`, isolated in `tools/phpbench`).
- CI: the SonarQube Cloud analysis also requires the repository variable `SONARCLOUD=enabled`, set once the
  project is imported on SonarQube Cloud.

- README: maintainer (The Bearded Bear), the other URSSAF packages and professional support; composer
  `authors` now points to https://the-bearded-bear.com.

- The package is open source under the MIT license (previously proprietary). Added `CONTRIBUTING.md` and
  `SECURITY.md`, and a notice in the README stating that the package is unofficial and not affiliated with URSSAF.
