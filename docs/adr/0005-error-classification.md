# ADR 0005 — Classification of URSSAF errors

Status: Accepted (2026-11-17). Relates to [ADR 0000](0000-reference-architecture.md).

## Context

PAJE errors carry a stable `code` (api-reference §1.3, §3.6), unlike the CESU `ERR_METIER` that had to be classified by
text. The payload is an array of `Erreur` for most operations and a single object for the mandate operations and the
PDF examples (§4 decision 6). Some PDF codes carry trailing spaces (`"PE_INEXISTANT "`), the technical error exists as
`ERR_TECHNIQUE` (500) and `ERREUR_TECHNIQUE` (400, hourly rate ≥ 100 €), and the `ER_API_DECLA_*` suffixes are written
with 4 or 5 digits in the PDF (§5).

## Decision

- `Wire\ErrorPayloadParser` accepts a list or a single object with `code` (§4 decision 6), and the envelope
  `{"errors": [...]}` inherited from the reference parser (not documented for PAJE, harmless); entries without a code
  are dropped; codes are trimmed (§4 decision 6) and inner whitespace is removed (inherited from the reference parser:
  the PDF breaks codes across lines).
  `parseBody()` is lenient (no errors on an unreadable body), `parsePayload()` strict.
- `Infrastructure\Urssaf\Exception\ApiErrorCode` lists every code of §3.6 (blocking and non-blocking) verbatim, with
  `fromCode(): ?self`; an unknown code is kept raw next to a `null` enum. Both 4- and 5-digit spellings of the
  `ER_API_DECLA_*` family are **not** invented: the enum holds the PDF spellings only, and the classifier also
  recognises the other digit count of the same family by numeric value (`ER_API_DECLA_0020` ≡ `ER_API_DECLA_00020`),
  until the sandbox settles §5.
- `Wire\ErrorClassifier` maps codes to port enums used by the Application: `RejectionKind` (`invalid_parameter`,
  `employer_not_found`, `birth_date_mismatch`, `mandate_missing`, `mandate_held_elsewhere`, `duplicate_declaration`,
  `declaration_window`, `employee_not_associated`, `business_rule`, `technical`, `unknown`).
  `duplicate_declaration` = `ER_API_050_0070` and `ER_API_DECLA_00020` (ADR 0003).
- Non-blocking messages (`erreursNonBloquantes`, HTTP 200) are decoded with the same code table into
  `Domain\Message\NonBlockingMessage` (ADR 0008).
- Descriptions are kept on the error objects but never put in exception messages or logs (they may echo personal
  values, ADR 0007).

## Consequences

- The Application never compares a wire string; adding a code is an Infrastructure change.
- The 4- / 5-digit tolerance is removed once a contract test proves the actual spelling.

## Amendment (2026-11-17, lead-accepted, lot 2)

- `RejectionKind` gains `employee_birth_date_mismatch` (`ER_API_021_0010`). `birth_date_mismatch` now means only
  `ER_API_020_0000`: the birth date of the **employer** reference, which the application takes from persisted
  credentials. The employee mismatch is an input problem of the host and is handled apart. The kind sits right after
  `birth_date_mismatch` in the priority order.
- `ER_API_DECLA_0002` (limit of declarations for non-certified employees) is a `business_rule`, no longer a
  `declaration_window`: it is not a matter of period.
- `ErrorPayloadParser::parseBody()` (lenient) skips the entries of a list that are not objects instead of discarding the
  whole body, so that one malformed entry never hides a duplicate-declaration code. `parsePayload()` stays strict.
- Non-blocking codes go through the same normalisation as error codes (inner whitespace removed).

## Amendment (2026-11-17, lead-accepted, lot 2)

- `ERREUR_TECHNIQUE` (HTTP 400, computed hourly rate of 100 EUR or more on 045 / 046 / 050 / 051, api-reference §1.2 and
  §3.6) is a `business_rule`, no longer `technical`: it is a deterministic rejection of the input, so retrying the same
  request can never succeed and it is never retried. Only `ERR_TECHNIQUE` (HTTP 500) stays `technical`.
