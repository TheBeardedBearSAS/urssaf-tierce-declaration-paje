# ADR 0001 — Date-only encoding and business time zone

Status: Accepted (2026-11-17). Relates to [ADR 0000](0000-reference-architecture.md).

## Context

Every date of the PAJE contract is `format: date` (`yyyy-MM-dd`): employer and employee birth dates, child birth dates,
licence start, period bounds, payment date, planned debit / payment dates, transfer date (api-reference §1, §4
decision 4). The reference sends calendar dates as `YYYY-MM-DDT00:00:00Z`. The official sample responses use the
placeholder `"string"` for dates (`dtPrelevementPrevisionnelle`, `dtVersementPrevisionnelle`), which §4 decision 4
says the mapper must refuse.

## Decision

- `Domain\Shared\CalendarDate` is the only date type on the wire, encoded and decoded as strict `Y-m-d`
  (`Wire\DateEncoding`, `checkdate`). A value with a time part, an offset or any other text (the `"string"`
  placeholder included) is refused with `UnexpectedUrssafResponse`. The fixtures keep the official placeholders and
  drive rejection tests; positive tests use `*_valid.json` variants built from the official example values.
- No local rule compares a date with "today" (ADR 0009: the declaration window is server-side). The business time
  zone (default `Europe/Paris`, `WorkflowPolicy::businessTimeZone()`, bundle `workflow.business_timezone`) is only
  used to present instants (for example the month of a stale record) and by the Symfony scheduler.
- `Domain\Shared\Instant` (UTC, second precision) is used for local tracking only (created / updated / claimed at,
  events, outbox).

## Consequences

- No `T00:00:00Z` on the wire; the reference `DateEncoding` is not reused as is.
- A response of 050 / 051 carrying an invalid date cannot be read: it becomes an unknown outcome (ADR 0003), never a
  silent success.
