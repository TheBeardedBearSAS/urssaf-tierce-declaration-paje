# Security review, November 2026

| | |
|---|---|
| Date | 2026-11-17 |
| Reviewer | The project maintainer, assisted by an AI code assistant (Claude) |
| Scope | `thebeardedbear/urssaf-tierce-declaration-paje`, commit `13dd278125e3477613e79014b8b9b0280038e249` (`main`, after release v0.1.0): everything under `src/`, the Composer configuration, the GitHub workflows, composite action and Dependabot configuration, and the repository protection settings |
| Reference | [Assurance case](assurance-case.md): security requirements SR1 to SR7, threat model and trust boundaries |
| Next review | At the latest in November 2027, or earlier after a change of a trust boundary |

## Method

1. **Design review against the security requirements and the trust boundaries** of the assurance case: for each
   boundary (host to package, package to URSSAF, package to database, package to cache, package to logs, pipeline to
   consumers) the data crossing it was identified and the control that protects it was located in the code.
2. **Manual code review** of the security-relevant code: credentials and token handling (`Application/Port/Credentials`,
   `AccessToken`, `Infrastructure/Urssaf/Http/OAuth2TokenProvider`, `Infrastructure/TokenStore/*`), the HTTP client and
   URL building (`JsonApiClient`, `ApiConfiguration`, `Environment`), the decoding and encoding of untrusted payloads
   (`Infrastructure/Urssaf/Wire/*`), validation of employer, employee, child and declaration data (`src/Domain/**`),
   the declaration workflow (`DeclarationSubmitter`, retry classes of `Operation`, erasure), logging and exception
   messages (every `logger->` call), randomness (`Infrastructure/Id/*`), Doctrine repositories and mappings, the outbox
   and the integration event codec, the Symfony bundle configuration, and the diagnostics.
3. **Targeted searches** for dangerous constructs in `src/`: `unserialize`, `serialize`, `eval`, `exec`, weak hashes
   and random functions, TLS options, `http://` literals, `var_dump` / `print_r`, direct reads of the credentials.
4. **CI and supply-chain review**: the four workflows, the composite setup action, Dependabot configuration, release
   signing, and the settings of the GitHub repository (ruleset of `main`, secret scanning, workflow token defaults),
   read through the GitHub API.
5. **Tool support** (complements, does not replace the human review): `composer audit --locked` run on the lock file
   during this review (no advisory found); the standing gates PHPStan level max, Mago, Deptrac, 100% coverage,
   Infection at 100% MSI, PHP-CS-Fixer and Rector run on every pull request; CodeQL on the workflows, OpenSSF Scorecard
   and SonarQube Cloud as described in the assurance case.

Not done: no dynamic testing (fuzzing, penetration test) and no review of the third-party development tools beyond
`composer audit`. The live URSSAF sandbox was not exercised (the contract suite has not been run yet, see the
[roadmap](../../ROADMAP.md)).

## Result

No vulnerability exploitable in the default or the documented configuration was found. The review recorded twelve
observations, all low or informational, listed below. The main design properties were confirmed: no third-party runtime
code, secrets never logged nor serialised, personal data (NIR, identities, child data) never persisted, strict validation
at construction, strict typed decoding of responses, exact money representation, declarations never resent after an
unknown outcome, consistent tenant scoping in persistence, pinned and least-privilege CI.

## Findings

Severity scale: Critical, High, Medium, Low, Informational. Status: Open (accepted for a later hardening change),
Accepted (residual risk assumed by design or by the host), Mitigated.

| ID | Severity | Description | Status |
|---|---|---|---|
| SR-01 | Low | The base URI override (`ApiConfiguration`, bundle option `base_uri`) accepts `http://` as well as `https://` (`BASE_URI_PATTERN`). An `http://` URL would send the client secret, the token and the query strings (employer number, NIR, birth dates) in clear. The default environments are HTTPS and the override is an operator setting, but a guard (refuse non-HTTPS outside a loopback host) is cheap. Redirect handling and TLS verification are those of the PSR-18 client provided by the host. | Open |
| SR-02 | Low | The response body is read in full (`(string) $response->getBody()` in `JsonApiClient` and `OAuth2TokenProvider`) and decoded with a depth limit of 512, without a size cap. A malicious or compromised endpoint could force a large allocation. Mitigated by the client timeout and PHP `memory_limit`; the cap would have to be applied on the stream. | Open |
| SR-03 | Low | The outbox stores integration events with PHP `serialize()` and reads them with `unserialize()` restricted by `allowed_classes` to the eight scalar event classes (`IntegrationEventCodec`). Not exploitable through URSSAF input, and an attacker able to write the outbox table already controls the database, but a plain JSON codec would remove the class of risk and the coupling of stored data to class layout. | Open |
| SR-04 | Low | `PsrLoggerFailureReporter` logs `Throwable::getMessage()` of a failure raised by a host event listener after a commit. The package's own messages are free of secrets and personal data, but a host exception message is outside its control. Logging the exception class only would be consistent with the rest of the package (the outbox already stores only the class and the code of a sink failure, `RelayOutboxHandler`). | Open |
| SR-05 | Informational | URSSAF error `message` and `description` are stored verbatim in the database (`urssaf_paje_submission_errors`, column of the declaration record) and may echo submitted values (the templates are filled with host values). They are not logged, not part of exception messages, and masked in debug output (`SubmissionError`, ADR 0007); retention and anonymisation belong to the host (`ForgetEmployer`). | Accepted |
| SR-06 | Informational | The access token is stored in clear in the PSR-6 / PSR-16 pool for its lifetime (default pool `cache.app`, often a filesystem directory). Short lived and bound to one client id, but the host must keep the pool private. Listed in the assumptions of the assurance case. | Accepted |
| SR-07 | Informational | The employer Pajemploi number and birth date, and the mandate reference, are stored in clear columns by default; the birth date has its own DBAL type so that it can be encrypted alone (`docs/doctrine.md`). A test asserts that no column exists for the NIR, names, address or child data. Database encryption at rest, access control and backup retention belong to the host. | Accepted |
| SR-08 | Informational | `client_secret` handling depends on the host: `%env()%` placeholders are documented, but a literal value in committed configuration is accepted by the bundle and would end in the compiled container. A host-side secret store is assumed. | Accepted |
| SR-09 | Informational | The default rate limiter is per process (`SlidingWindowRateLimiter`): several workers can together exceed the URSSAF quotas (a 429 is retryable, nothing is lost). A shared limiter can be injected (`GatewayOptions`); this is an availability concern, not a confidentiality or integrity one. | Accepted |
| SR-10 | Informational | The URSSAF contract carries the employer number, the NIR and birth dates in the query string of operations 020, 021, 023, 030 and 031. The package never logs the URI and drops the message of a transport exception (ADR 0002, ADR 0007), but the host's HTTP client, its proxies and URSSAF's own access logs may record them. Imposed by the API contract. | Accepted |
| SR-11 | Low | Repository settings: the ruleset on `main` requires pull requests, 19 status checks, forbids deletion and force pushes and has no bypass actor, but requires 0 approving reviews (single maintainer), the `dco` and `coverage-branches` jobs are not in the required checks (the latter does not run on pull requests), and Dependabot security updates are disabled (Dependabot version updates for Composer and GitHub Actions are configured, and `composer audit` runs in CI). The default workflow token is read-only, but workflows are allowed to approve pull requests. The checkouts of the `ci.yml` jobs other than `dco` keep the default `persist-credentials: true` (the token is read-only; the CodeQL, Scorecard and release checkouts disable it). CodeQL does not analyse PHP: PHP code security relies on PHPStan, Mago and SonarQube Cloud. | Open |
| SR-12 | Informational | Identifier validation is deliberately light: the NIR (`Nir`) is checked for its length of 15 characters only (no format or key check), and the employer and employee numbers for their 14-character length (the charset is not enforced, `docs/roadmap.md`). URSSAF remains the authority and rejects an unknown identifier with a business error; the package only guards against malformed input shapes. | Accepted |

No finding of severity Medium or higher.

## Confirmed controls (positive results)

- `composer.json` requires only PSR interfaces and PHP extensions at runtime; the lock file has no known advisory.
- Credentials: private constructor and validation, masked `__debugInfo()`, `#[SensitiveParameter]`, serialisation
  refused (`CredentialsNotSerializable`); the secret is read only to build the token request.
- Logging: only operation, method, path template (employer number replaced), status, duration, request id and exception
  class are logged by the package; URIs, query strings, bodies, tokens and personal data are not.
- Transport exceptions keep only their class (`TransportError`); the `JsonException` of a malformed body is dropped,
  never chained.
- Exception messages carry codes, statuses and field names, never the rejected value.
- The NIR, employee identity and address and child data are not persisted (`PersonalDataColumnsTest`); value objects
  holding them are masked in dumps.
- All validation regexes are anchored and use the `D` modifier; no catastrophic-backtracking pattern was identified.
- Responses are decoded with `json_decode` into arrays and read through typed accessors; an unknown status or invalid
  JSON raises `UnexpectedUrssafResponse`.
- Declarations (050, 051) are never resent automatically: transport error, 5xx or unreadable answer is an
  `OutcomeUnknown`; the claim of a record is atomic and an accepted answer is never discarded.
- Randomness comes from `random_bytes()`; weak generators are absent from `src`.
- Persistence uses named parameters; the only interpolated identifier is the outbox table name from Doctrine metadata.
- Tenant scoping is applied in repository queries; locks and token cache keys are per tenant or per client.
- The simulated gateway is refused in the production environment, at configuration time and at runtime.
- Workflows: pinned action SHAs, read-only default permissions, scoped elevation, no `pull_request_target`, no
  free-text event data interpolated in scripts (only commit SHAs and secrets through `env`), SLSA provenance and checksums on releases.

## Follow-up

The `Open` findings are hardening opportunities, to be tracked as issues: a guard on non-HTTPS base URIs (SR-01), a stream
size cap (SR-02), a JSON outbox codec (SR-03), class-only logging in `PsrLoggerFailureReporter` (SR-04), and the
repository settings of SR-11. Any of them that changes behaviour comes with tests, as required by
[CONTRIBUTING.md](../../CONTRIBUTING.md).
