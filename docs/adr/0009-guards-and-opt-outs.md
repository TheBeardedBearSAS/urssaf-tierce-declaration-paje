# ADR 0009 — Local guards and their opt-outs

Status: Accepted (2026-11-17). Relates to [ADR 0000](0000-reference-architecture.md).

## Context

The specification does not say which operations require a registered mandate (api-reference §4 decision 8, §5): the
recommended order of §2 is *inferred*, and `MANDAT_INEXISTANT` only appears as an example of 040 (§2.5). §4 decision 11
asks that only contract rules be enforced locally, with the laxer bound when the PDF and the Swagger disagree; the
project rules forbid inventing a rule.

## Decision

- **Mandate guard (off by default).** `Application\Service\MandateGuard` can refuse the declaration operations 045,
  046, 050 and 051 (`MandateRequired`) when no non-cancelled local mandate exists for the employer. These four are
  chosen because they act on the employer's payroll on its behalf, the reason a mandate exists (§2.11); the guard is a
  host safety net, not a URSSAF rule. `WorkflowPolicy::requireLocalMandate()` defaults to `false` (bundle
  `workflow.require_local_mandate`) until the sandbox settles §5; a command can also set `skipMandateCheck`. An
  employer mandated outside the package passes after `ImportMandate` (ADR 0006). `MANDAT_INEXISTANT` from URSSAF is
  always reported as `mandate_missing`.
- **Local validation = the Swagger schema + the explicit decisions of api-reference §4:**
  - Swagger types, `required` lists, `minLength` / `maxLength` (14 / 15 / 5 / 3 / 30 / 21 / 32 / 1), `pattern`,
    `minimum` / `maximum`, `multipleOf` (0.01, 0.5), applied field by field; where the PDF and the Swagger disagree,
    the laxer bound (`nbJoursActivite` 1–99 in declarations, `nbHeures` ≥ 1 without maximum in `InputDeclCommun`,
    `dtNaissanceSalarie` optional on 023, no administrative field required in `InputIdentificationSalarie`);
  - api-reference §2.7 ("treat them as required in practice"): `inputDeclCommun`, `inputSp` and `inputDeclEnfant` are
    required in the four declaration bodies;
  - §4 decision 10: at least one identification criterion for 021, 023 and `InputSp`;
  - §4 decision 4: strict `yyyy-MM-dd` dates.
- **PDF-documented codes not enumerated in the Swagger** (`cdModeCalcul`, `indcEnfantPlusJeuneAgarder`,
  `typeActiviteSalarie`, `cdCivilite`, `lettreVoie`, `typeVoie`, §5 "Enums not in Swagger") are value objects that
  accept any value matching the Swagger length / pattern, expose the PDF values as named constructors and report
  `isDocumented()`. Nothing outside the Swagger constraint is refused locally. The AMA / GED split of the package is a
  choice of operation (`/ama/…` vs `/ged/…`), not a validation of `typeActiviteSalarie`.
- **Server-side only** (documented, never checked locally): every PDF-only rule — one child per AMA declaration
  (`ER_API_DECLA_00040`), `joursAccueil24Consecutive ≤ nbJoursActivite`, disabled-child days, hourly rate required
  with major / complementary hours, `libelleVoie` with `numVoie`, at least one address line, `dtDebutAgrement` for
  `A`, PDF-mandatory administrative fields of a creation, declaration window, SMIC and salary ceilings, `NMX`, hourly
  rate ≥ 100 €, specific hours, duplicates. Whether 023 creates an employee is decided by the server, so the package
  cannot know in advance.

## Consequences

- A local refusal never reaches URSSAF, and the package never refuses a request the contract accepts.
- When the sandbox settles the mandate prerequisite, only the default changes.
