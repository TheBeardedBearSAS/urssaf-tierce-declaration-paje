# Implementation roadmap

Specification: [api-reference.md](api-reference.md). Architecture, conventions and quality gates are the same as in
`thebeardedbear/urssaf-tiers-prestations` (hexagonal layers enforced by Deptrac, PSR-only runtime, Symfony and
Doctrine bridges, 100 % coverage and MSI).

> Status (2026-11-17): steps 1 to 7 below are implemented on `feat/implementation` (one commit per lot; step 4 in two
> commits). What remains: running the contract suite on the sandbox to settle api-reference §5, then a first release.

## Already in place (skeleton)

- `SpecVersion::CURRENT` (`1.5.2`).
- `Infrastructure\Urssaf\Environment` (Sandbox `https://api-edi.urssaf.fr` / Production `https://api.urssaf.fr` base
  URIs) and `Infrastructure\Urssaf\UrssafEndpoints` (token path `/api/oauth/v1/token`, scope `tiersdecl.paje`, base path
  `/tiersdecl/v1/paje`).

## Domain (`src/Domain`)

- `Employer\PajeEmployerNumber` (14 characters; the alphanumeric charset is not enforced), `Employer\EmployerReference` (number + birth date),
  `Employer\OnlineAccountState` (`Actif`, `Absent`, `Inactif`, bank details invalid; raw fallback).
- `Employee\PajeEmployeeNumber` (14 chars), `Employee\Nir` (15 chars, never logged), `Employee\EmployeeIdentification`
  (sum type: by number, by NIR, by administrative data; `InputIdentificationSalarie` fields), `Employee\ActivityType`
  (`A` AMA, `G` GED), `Employee\Civility`, `Employee\Address` (street letter / street type enums, api-reference §3.3-§3.4),
  `Employee\EmployeeAccount` (verification result: number, AMA / GED flags, account state),
  `Employee\EmployeeAssociation` (created / associated / already associated + warnings).
- `Child\Child` (name, first name, birth date; upper-case no-accent pattern), `Child\ChildEntitlementStatus`
  (`0` / `1` / `2`, api-reference §3.1), `Child\ChildCheck`.
- `Declaration\DeclarationPeriod` (start, end, payment date), `Shared\Amount` (signed integer cents, unbounded; the bounds, mostly ≤ 9999.99, are applied per field by
  `Shared\AmountRules`),
  `Declaration\CalculationMode` (`R` / `F` / `S`), `Estimate\YoungestChildAge` (`1` / `2` / `3`),
  `Declaration\ChildminderDeclaration` and `Declaration\HomeCareDeclaration` (the two request aggregates, local
  validation of ranges), `Declaration\ChildminderContractEnd` and `Declaration\HomeCareContractEnd`, `Declaration\DeclarationAmounts`
  (`DeclarationCommun`), `Declaration\PreDeclarationResult`, `Declaration\DeclarationResult` (adds `referenceDocumentaire`).
- `Estimate\ContributionEstimate`, `Estimate\ChildminderEstimateRequest`, `Estimate\HomeCareEstimateRequest`.
- `Mandate\Mandate` (employer reference, registered / cancelled).
- `Message\NonBlockingMessage` (code, message, description; known codes api-reference §3.6, raw fallback).
- `Domain\Exception\*`.

## Application (`src/Application`)

- Port `Port\Urssaf\PajeGateway` (one method per operation: `verifyEmployer`, `verifyEmployee`, `verifyChildren`,
  `associateEmployee`, `estimateChildminder`, `estimateHomeCare`, `preDeclareChildminder`, `preDeclareHomeCare`,
  `declareChildminder`, `declareHomeCare`, `registerMandate`, `cancelMandate`) — possibly split in three ports
  (accounts / declarations / mandates) if Deptrac or readability asks for it.
- Ports `CredentialsProvider`, `TokenStore` (same contracts as Tiers de prestations).
- Optional persistence ports (Doctrine bridge): `EmployerRepository`, `MandateRepository`, `DeclarationRepository`.
- Commands / queries with handlers: `RegisterMandate`, `CancelMandate`, `VerifyEmployer`, `VerifyEmployee`,
  `VerifyChildren`, `AssociateEmployee`, `EstimateContributions` (AMA / GED), `PreDeclareSalary` (AMA / GED),
  `DeclareSalary` (AMA / GED; never retried automatically, api-reference §4 decision 7).

## Infrastructure (`src/Infrastructure`)

- `Urssaf\Http\OAuth2TokenProvider`, `JsonApiClient`, `RetryAfter` (reuse the Tiers de prestations design; retries
  disabled on `declare*`).
- `Urssaf\UrssafHttpGateway` + `Urssaf\Wire\*` mappers (dates `yyyy-MM-dd`, decimal-safe amounts, empty body `{}`,
  query vs body parameters per operation, optional `erreursNonBloquantes`).
- `Urssaf\Exception\ApiErrorCode` (api-reference §3.6, raw fallback), error payload parser (array **or** single object,
  codes trimmed).
- `ApiConfiguration` (environment + overridable base URI).

## Bridges

- Symfony bundle `UrssafTierceDeclarationPajeBundle` (alias `urssaf_tierce_declaration_paje`): configuration
  (credentials, environment, base URI), HTTP client, token cache pool, console commands (e.g.
  `urssaf:paje:verify-employer`, `urssaf:paje:estimate`, `urssaf:paje:predeclare`).
- Doctrine bridge (available, `docs/doctrine.md`): persistence of the employers (number, mandate state), mandates (registered / cancelled, platform
  side) and declarations (period, employee, result amounts, `referenceDocumentaire`, status) to track what was sent and
  protect against double declaration. Entities and mappings stay in the bridge, the domain stays framework-free.

## Runtime dependencies to add with the code

`ext-json`, `ext-mbstring`, `psr/http-client`, `psr/http-factory`, `psr/http-message`, `psr/clock`, `psr/log`,
`psr/simple-cache` (or `psr/cache`), plus the matching `suggest` entries (Symfony and Doctrine bridges).

## Tests

- Unit: value objects (ranges, patterns, 14 / 15 / 5-char lengths, `Amount`), mappers against
  `tests/Fixtures/wire/*.json` (requests serialised, responses parsed, error payloads in both shapes), handlers with a
  fake gateway.
- Functional: Symfony bundle wiring; Doctrine mappings.
- Contract (`make test-contract`, manual CI job; implemented, see [testing.md](testing.md)): sandbox credentials
  `URSSAF_SANDBOX_CLIENT_ID/SECRET`, optional `URSSAF_SANDBOX_BASE_URI` and `URSSAF_SANDBOX_TOKEN_URI` (when set, every SDK token request goes there; the production host is refused); a sandbox test employer (number + birth date)
  with a registered mandate, a test employee, a test child (environment (or repository) variables). First goals: settle the mandate
  prerequisite, the `ER_API_DECLA_*` code suffixes and the 023 required-fields open points of api-reference §5. The
  first run against the sandbox is still to do.

## Draft and factory layer

ADR 0000 §4 ("factories collect every violation of a draft at once") is not in lot 1: each value object throws on its
own and the aggregates collect only the range violations of their own fields (nested results are not prefixed). The
single-shot entry point (raw host input to aggregates, nested paths such as `common.hours`, using the raw parsing API of
`Validation\ViolationCollector`: `required()`, `optional()`, `requiredInteger()`, `requiredBool()`, `merge()`) belongs to
step 4 below, with the Application commands; the factories are in `Domain\*\Input` (ADR 0000, amendment lot 4b).

## Order of work

1. Domain value objects (identifiers, `Amount`, child, period) and result types.
2. Wire mappers + fixtures (requests and responses of the 12 operations, error payloads).
3. HTTP gateway (OAuth2, error parsing, 429 / 503 handling, no retry on `declare*`).
4. Application commands and queries: mandate, verifications, association, estimates, pre-declaration, declaration.
   - Binding (ADR 0009 amendment): the association command (023) with `EmployeeByAdministrativeData` requires a non-empty
     `EmployeeCreation::identity()` (the identity is the only matching data, it is not sent in the query), with a stable
     violation code. `AssociateEmployeeRequestMapper` does not enforce it.
5. Symfony bridge.
6. Doctrine bridge (employers, mandates, declarations tracking).
7. Contract tests (suite written), then run it against the sandbox and remove the settled open points from `api-reference.md`.

## Out of scope

The CESU, PAJE and Auto-entrepreneur tierce-déclaration APIs share the `/tiersdecl/v1` prefix, the OAuth2 flow and the
mandate model. A shared library could be extracted later; it is not part of this package.
