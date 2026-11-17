# ADR 0011 — Quotas and rate limiting

Status: Accepted (2026-11-17). Relates to [ADR 0000](0000-reference-architecture.md).

## Context

PDF §2.5 describes two quota levels (global per API, per API client) but publishes **no value** (api-reference §1.1).
The reference limits calls per operation per minute with published values; the CESU package uses its published hourly
quota.

## Decision

- `Application\Port\RateLimiter::acquire(TenantId, Operation)` is kept. The `UrssafGatewayFactory` default is
  `NoopRateLimiter`: no value is invented, the URSSAF quota applies as HTTP 429.
- `SlidingWindowRateLimiter` (per tenant, all operations together, token calls not counted) is available with a
  host-provided limit and window (`GatewayOptions::withRateLimiter()`, bundle `rate_limiter.enabled` default `false`,
  `rate_limiter.limit`, `rate_limiter.window`). It is per process (documented).
- 429 is a `UrssafUnavailable` with `isQuotaExceeded()`, retryable on every operation, `Retry-After` honoured when
  present. A local refusal is a retryable `RateLimitExceeded`.

## Consequences

- When URSSAF publishes quota values, only the defaults change.
