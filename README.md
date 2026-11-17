# URSSAF "API Tierce Déclaration PAJE" for PHP

<!-- the-bearded-bear:badges:start -->
[![CI](https://github.com/TheBeardedBearSAS/urssaf-tierce-declaration-paje/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/TheBeardedBearSAS/urssaf-tierce-declaration-paje/actions/workflows/ci.yml?query=branch%3Amain) [![Coverage](https://codecov.io/gh/TheBeardedBearSAS/urssaf-tierce-declaration-paje/branch/main/graph/badge.svg)](https://app.codecov.io/gh/TheBeardedBearSAS/urssaf-tierce-declaration-paje) [![Mutation score](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2FTheBeardedBearSAS%2Furssaf-tierce-declaration-paje%2Fmain)](https://dashboard.stryker-mutator.io/reports/github.com/TheBeardedBearSAS/urssaf-tierce-declaration-paje/main) [![Quality Gate](https://sonarcloud.io/api/project_badges/measure?project=TheBeardedBearSAS_urssaf-tierce-declaration-paje&metric=alert_status)](https://sonarcloud.io/summary/new_code?id=TheBeardedBearSAS_urssaf-tierce-declaration-paje) [![Maintainability](https://sonarcloud.io/api/project_badges/measure?project=TheBeardedBearSAS_urssaf-tierce-declaration-paje&metric=sqale_rating)](https://sonarcloud.io/summary/overall?id=TheBeardedBearSAS_urssaf-tierce-declaration-paje) [![Reliability](https://sonarcloud.io/api/project_badges/measure?project=TheBeardedBearSAS_urssaf-tierce-declaration-paje&metric=reliability_rating)](https://sonarcloud.io/summary/overall?id=TheBeardedBearSAS_urssaf-tierce-declaration-paje) [![Security](https://sonarcloud.io/api/project_badges/measure?project=TheBeardedBearSAS_urssaf-tierce-declaration-paje&metric=security_rating)](https://sonarcloud.io/summary/overall?id=TheBeardedBearSAS_urssaf-tierce-declaration-paje) [![OpenSSF Scorecard](https://api.scorecard.dev/projects/github.com/TheBeardedBearSAS/urssaf-tierce-declaration-paje/badge)](https://scorecard.dev/viewer/?uri=github.com/TheBeardedBearSAS/urssaf-tierce-declaration-paje)
[![PHPStan level max](https://img.shields.io/badge/PHPStan-level%20max-brightgreen)](phpstan.neon.dist) [![License: MIT](https://img.shields.io/badge/license-MIT-blue)](LICENSE)
<!-- the-bearded-bear:badges:end -->

`thebeardedbear/urssaf-tierce-declaration-paje` — client, domain model and Symfony integration for the URSSAF API that
lets a platform, on behalf of a private employer, estimate the contributions of a Pajemploi activity, declare the
salary paid to a licensed childminder (*assistante maternelle agréée*) or a home childcare worker (*garde d'enfants à
domicile*), and apply the withholding at source.

Namespace: `TheBeardedBear\UrssafTierceDeclarationPaje`.

Developed and maintained by [The Bearded Bear](https://the-bearded-bear.com).

> [!IMPORTANT]
> **Unofficial package.** This project is an independent open-source implementation of a public API. It is not
> developed, endorsed, certified or supported by URSSAF, and it is not affiliated with URSSAF in any way. "URSSAF" is
> used only to name the API this package talks to. Using this package does not replace the URSSAF habilitation
> process; the URSSAF documentation remains the authority on the API, and you remain responsible for the data your
> application sends to URSSAF.

> 🚧 **Status: implemented, not released yet.** The domain model, the HTTP gateway (OAuth2, quotas, error
> classification), the use cases (employers, mandates, declarations, unknown outcomes, outbox) and the framework-free
> wiring are implemented and covered by tests. The Symfony bundle is implemented ([docs/symfony.md](docs/symfony.md)); the Doctrine bridge is available ([docs/doctrine.md](docs/doctrine.md)): persistence is Doctrine
> (the default of the bundle when `doctrine/orm` is installed), in memory (tests) or yours. No version is tagged yet and the API may still change. See [docs/roadmap.md](docs/roadmap.md).

## Scope

| Operation | Endpoint |
|---|---|
| Check an employer account (PAJE020) | `GET /employeurs/{numeroPajeEmployeur}/verifier` |
| Check an employee account (PAJE021) | `POST /salaries/verifier` |
| Check the entitled children (PAJE022) | `POST /employeurs/{numeroPajeEmployeur}/enfants/verifier` |
| Associate an employee to an employer (PAJE023) | `POST /employeurs/{numeroPajeEmployeur}/salarie/associer` |
| Register a mandate (PAJE030) | `POST /mandats` |
| Cancel a mandate (PAJE031) | `DELETE /mandats` |
| Estimate contributions, childminder (PAJE040) | `POST /ama/estimer` |
| Estimate contributions, home childcare (PAJE041) | `POST /ged/estimer` |
| Pre-declare a childminder salary (PAJE045) | `POST /employeurs/{numeroPajeEmployeur}/ama/predeclarer` |
| Pre-declare a home childcare salary (PAJE046) | `POST /employeurs/{numeroPajeEmployeur}/ged/predeclarer` |
| Declare a childminder salary (PAJE050) | `POST /employeurs/{numeroPajeEmployeur}/ama/declarer` |
| Declare a home childcare salary (PAJE051) | `POST /employeurs/{numeroPajeEmployeur}/ged/declarer` |

OAuth2 client credentials, scope `tiersdecl.paje`, base URL `https://api.urssaf.fr/tiersdecl/v1/paje` (sandbox:
`https://api-edi.urssaf.fr/tiersdecl/v1/paje`).

## Quick start (standalone)

Without a framework, the builder wires every use case from a PSR-18 client, PSR-17 factories and your credentials.
Persistence is in memory by default (tests and prototypes); [docs/standalone.md](docs/standalone.md) lists the options and
[docs/workflow.md](docs/workflow.md) explains the lifecycles. All values below are fictitious.

```php
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\ImportEmployer;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\RegisterMandate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\EmployerSelector;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Credentials;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\PajeEmployerNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Credentials\StaticCredentialsProvider;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Environment;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\HttpTransport;
use TheBeardedBear\UrssafTierceDeclarationPaje\Standalone\UrssafTierceDeclarationPajeBuilder;

// $httpClient is your PSR-18 client, $psr17Factory a PSR-17 request and stream factory.
$urssaf = UrssafTierceDeclarationPajeBuilder::create(
    new HttpTransport($httpClient, $psr17Factory, $psr17Factory),
    new StaticCredentialsProvider(['tenant-1' => Credentials::of('my-client-id', 'my-client-secret')]),
    ApiConfiguration::forEnvironment(Environment::Sandbox),
)->build();

$tenant = TenantId::fromString('tenant-1');
$employer = EmployerReference::of(PajeEmployerNumber::of('Y0000000000001'), CalendarDate::fromString('1980-01-01'));
$reference = ExternalReference::fromString('employer-42');

// Record an employer you already know (no call to URSSAF), then register the mandate you hold for it (030).
$urssaf->importEmployer()(new ImportEmployer($tenant, $reference, $employer));
$mandate = $urssaf->registerMandate()(new RegisterMandate($tenant, EmployerSelector::byExternalReference($reference)));

$state = $mandate->state; // "registered"
```

Declaring a salary (`declareSalary()`), estimating contributions, checking the employer, the employee or the children,
resolving an unknown outcome and the safety crons are covered in [docs/standalone.md](docs/standalone.md) and
[docs/workflow.md](docs/workflow.md). In tests, `Testing\SimulatedPajeGateway` replaces the HTTP gateway and never
contacts URSSAF.

## Documentation

- [docs/api-reference.md](docs/api-reference.md) — technical reference (single source of truth for the package).
- [docs/standalone.md](docs/standalone.md) — wiring without a framework, scheduling, testing with the simulator.
- [docs/symfony.md](docs/symfony.md) — Symfony bundle: configuration, commands, Messenger, scheduler, locks, diagnostics, translations.
- [docs/workflow.md](docs/workflow.md) — lifecycles, outcome and re-entry tables, unknown outcomes, safety crons.
- [docs/testing.md](docs/testing.md) — test suites, fakes, quality gates and the sandbox contract suite.
- [docs/roadmap.md](docs/roadmap.md) — planned architecture and order of work.
- [docs/adr/](docs/adr/README.md) — architecture decision records.
- [docs/openapi/](docs/openapi/) — official OpenAPI contract(s) from the URSSAF API portal.
- `docs/reference/` — official URSSAF documentation: not versioned (URSSAF documents), download it from the links in [docs/api-reference.md](docs/api-reference.md) into this local, git-ignored folder.
- Catalogue page: https://portailapi.urssaf.fr/fr/catalogue-api/prd/td-paje

## Requirements

PHP 8.2+. Development only needs Docker: every `make` target runs in a container.

## Development

```sh
make install     # build the image, install dependencies
make ci          # full quality gate: cs, rector, phpstan, mago, deptrac, composer checks, hadolint, guard, coverage, infection
make test        # unit + functional tests
make bench       # benchmarks (PHPBench, tests/Benchmark), added with the implementation
make test-contract  # live sandbox contract tests (skipped without credentials), see below
make help        # every target
```

`make ci PHP_VERSION=8.4` runs the gate on another PHP version.

### Contract tests (URSSAF sandbox)

`make test-contract` runs the live contract suite against the URSSAF sandbox (`https://api-edi.urssaf.fr`; the production
host is refused). It is never part of `make test` or `make ci`. Without credentials every live test is skipped and the
target passes. Export the variables in your shell (they are forwarded to the container, nothing is written to a file):

| Variable | Purpose |
|---|---|
| `URSSAF_SANDBOX_CLIENT_ID`, `URSSAF_SANDBOX_CLIENT_SECRET` | OAuth2 credentials (secrets); without them every live test is skipped |
| `URSSAF_SANDBOX_BASE_URI`, `URSSAF_SANDBOX_TOKEN_URI` | optional host overrides (never `api.urssaf.fr`); when set, `URSSAF_SANDBOX_TOKEN_URI` receives every SDK token request, and the token test also probes the derived URI first |
| `URSSAF_SANDBOX_EMPLOYER_NUMBER`, `URSSAF_SANDBOX_EMPLOYER_BIRTH_DATE` | sandbox test employer (both or none) |
| `URSSAF_SANDBOX_EMPLOYEE_NUMBER` | sandbox test employee |
| `URSSAF_SANDBOX_CHILD_LAST_NAME`, `URSSAF_SANDBOX_CHILD_FIRST_NAME`, `URSSAF_SANDBOX_CHILD_BIRTH_DATE` | sandbox test child (all three or none) |
| `URSSAF_SANDBOX_ALLOW_WRITES` | `1` also runs the tests that write sandbox state (030, 031, 023, 045, 046, 050, 051) |

A test whose variables are missing is skipped. The run writes `var/contract/sandbox-report.json` (statuses and error codes
only, never a body, a number, a name or a date). The same variables feed the manual `contract` job of the CI workflow
(secrets for the client id and secret, environment (or repository) variables for the others, a `contract_writes` input to write). Details
and the goals of the run: [docs/testing.md](docs/testing.md).

Once the repository is public, `main` publishes its quality proofs, linked from the badges at the top of this file: the
coverage report on Codecov, the full mutation report on the Stryker Dashboard, the SonarQube Cloud quality gate and the
OpenSSF Scorecard. The benchmarks (`tests/Benchmark`, PHPBench in `tools/phpbench`, out of the package dependencies)
run on every pull request against the base commit, and the job fails on a regression of more than 20 %.

## Versioning

Semantic Versioning. `SpecVersion::CURRENT` holds the version of the official URSSAF documentation implemented.

<!-- the-bearded-bear:start -->
## Other URSSAF packages

The same architecture, quality gate and conventions, one package per URSSAF API (overview: [the-bearded-bear.com/open-source/urssaf](https://the-bearded-bear.com/open-source/urssaf)):

| API | Package | Status |
|---|---|---|
| Tiers de prestations (immediate tax-credit advance) | [`thebeardedbear/urssaf-tiers-prestations`](https://github.com/TheBeardedBearSAS/urssaf-tiers-prestations) | Released (Packagist) |
| Attestations Vérification Code Sécurité (certificate verification) | [`thebeardedbear/urssaf-attestations-verification-code-securite`](https://github.com/TheBeardedBearSAS/urssaf-attestations-verification-code-securite) | Released (Packagist) |
| DPAE (pre-employment declaration) | [`thebeardedbear/urssaf-dpae`](https://github.com/TheBeardedBearSAS/urssaf-dpae) | Coming soon |
| Mobilité internationale (posting of workers abroad) | [`thebeardedbear/urssaf-mobilite-internationale`](https://github.com/TheBeardedBearSAS/urssaf-mobilite-internationale) | Coming soon |
| Plateforme numérique (withholding of contributions by digital platforms) | [`thebeardedbear/urssaf-plateforme-numerique`](https://github.com/TheBeardedBearSAS/urssaf-plateforme-numerique) | Released (Packagist) |
| Tierce Déclaration auto-entrepreneur | [`thebeardedbear/urssaf-tierce-declaration-auto-entrepreneur`](https://github.com/TheBeardedBearSAS/urssaf-tierce-declaration-auto-entrepreneur) | Coming soon |
| Tierce Déclaration CESU | [`thebeardedbear/urssaf-tierce-declaration-cesu`](https://github.com/TheBeardedBearSAS/urssaf-tierce-declaration-cesu) | Released (Packagist) |

## Professional support

This package is developed and maintained by [The Bearded Bear](https://the-bearded-bear.com).
If you need help with an URSSAF integration, The Bearded Bear can help with:

- the URSSAF habilitation and homologation process (subscription, sandbox tests, move to production);
- integrating the package into your application (Symfony, Doctrine or framework-free);
- support with a guaranteed response time, including the follow-up of URSSAF API changes;
- custom developments on top of the package.

Project page and integration offer: [the-bearded-bear.com/open-source/urssaf-tierce-declaration-paje](https://the-bearded-bear.com/open-source/urssaf-tierce-declaration-paje).
<!-- the-bearded-bear:end -->

## Contributing and security

Contributions are welcome: see [CONTRIBUTING.md](CONTRIBUTING.md). Report vulnerabilities privately as described in
[SECURITY.md](SECURITY.md), never in a public issue.

## License

MIT. See [LICENSE](LICENSE). The software is provided "as is", without warranty of any kind.

The API specification summarised in [docs/api-reference.md](docs/api-reference.md) belongs to URSSAF; see the sources
listed there.
