# ADR 0008 — Amounts, rates, non-blocking messages and the pre-declaration check

Status: Accepted (2026-11-17). Relates to [ADR 0000](0000-reference-architecture.md),
[ADR 0003](0003-retry-and-unknown-outcome.md), [ADR 0005](0005-error-classification.md),
[ADR 0009](0009-guards-and-opt-outs.md).

## Context

- Wire amounts are JSON numbers in euros (`multipleOf: 0.01`); the domain uses integer cents (§4 decision 3).
  `json_decode` yields floats, so the exact decimal text is not available after decoding.
- Request amounts have `maximum: 9999.99`, but a `minimum` only on some fields (`mntIndemnitesEntretien` 0,
  `mntSalaireNetMensuel` > 0 in `InputDeclCommun`; §5 notes `mntIndemnitesRepas` has none).
- Response amounts (`DeclarationCommun`, `OutputEstimation`) and `txPas` have no published bounds or precision (§5).
- A 200 response may carry `erreursNonBloquantes` (§4 decision 5).
- §4 decision 7 leaves open a "pre-declare then declare" helper.

## Decision

- **One amount type.** `Domain\Shared\Amount` is a signed integer number of cents (`fromCents`, `fromDecimalString`
  strict: optional sign, at most 2 decimals; `toDecimalString`). Bounds are not in the type: each request value object
  applies the Swagger `minimum` / `maximum` of its field (ADR 0009). Encoding goes through `JsonDecimal` (exactly 2
  decimals).
- **Decoding amounts.** A JSON number is rendered with `round($v, 2)` then `%.2F` (the shortest round-trip
  representation of a 2-decimal float is exact; this is how the CESU package reads amounts), then parsed as an
  `Amount`. No sign or bound is checked on responses: a declaration recorded by URSSAF never becomes an unknown outcome
  because of a computed amount. Integers are taken as is; non-finite values are refused.
- **Rates.** `txPas` uses `Domain\Shared\Rate`, an exact decimal string built from the shortest round-trip
  representation of the JSON number (PHP `serialize_precision = -1`), with no precision cap; it is stored and returned
  as text.
- **Half days.** `nbJoursCongesPayes` uses `HalfDays` (multiple of 0.5, 0–99).
- **Non-blocking messages.** Every result (`EmployeeAssociation`, `ContributionEstimate`, `PreDeclarationResult`,
  `DeclarationResult`) carries `list<NonBlockingMessage>` (raw code, nullable typed code, message, description). They
  never fail a call and are stored with the declaration record.
- **Pre-declaration check (opt-in).** `WorkflowPolicy::preDeclareBeforeDeclaring()` (default `false`, bundle
  `workflow.predeclare_before_declaring`). When enabled, `DeclareSalary` calls 045 / 046 with the same payload after the
  claim, and settles the record with the **same outcome table as the declaration** (ADR 0003 / 0006): a duplicate code
  → `already_declared`; another 400 → `rejected` (without calling 050 / 051); 401 / 403 / 512 →
  `invalid_credentials`; a retryable failure (429, transport, 5xx, rate limit, token) → `error`. 045 / 046 record
  nothing, so they never produce `outcome_unknown`. Non-blocking messages do not block. Default off: the extra call
  doubles the quota usage and the specification does not require it.

## Consequences

- api-reference §4 decision 3 is amended: amounts are read from the shortest round-trip representation rounded to the
  cent, not from the raw JSON text.
- Hosts that want a dry-run before every declaration flip one setting.
