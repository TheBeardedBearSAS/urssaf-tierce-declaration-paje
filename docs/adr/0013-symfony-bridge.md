# ADR 0013 — Symfony bridge

Status: Accepted (2026-11-17). Relates to [ADR 0000](0000-reference-architecture.md),
[ADR 0003](0003-retry-and-unknown-outcome.md), [ADR 0006](0006-local-lifecycles.md),
[ADR 0007](0007-personal-data.md), [ADR 0010](0010-tenant-model.md), [ADR 0012](0012-integration-events.md).

## Context

The package ships a Symfony 6.4 / 7.x bundle, as the reference does. PAJE has no payment request, no pairing and no
status polling; several URSSAF packages can be installed in one application; some command inputs are personal data.

## Decision

1. **Names.** Alias and configuration root `urssaf_tierce_declaration_paje`; service ids, parameters, tags and
   translation domain prefixed `urssaf_tierce_declaration_paje`; console namespace `urssaf:paje:`; lock prefix
   `urssaf_paje`; Monolog channel `urssaf_paje`; schedule `urssaf_paje`; recipe variables `URSSAF_PAJE_ENV`,
   `URSSAF_PAJE_CLIENT_ID`, `URSSAF_PAJE_CLIENT_SECRET`. Nothing is shared with the other URSSAF packages.
2. **Messenger scope.** Messages carry identifiers only and are safe to retry: refresh the employers of a tenant,
   refresh one employer (020). No declaration message (`DeclareSalary` is synchronous and the record never holds the
   payload, ADR 0006/0007), no 023 / 030 / 031 message (an answer is needed, ADR 0003), no auto-submit.
3. **Persistence drivers.** `in_memory`, `custom`, `doctrine`; `doctrine` is the default when `doctrine/orm` is
   installed (`in_memory` otherwise) and fails at container build without DoctrineBundle. `custom` accepts
   `persistence.transaction_manager`.
4. **Configuration paths.** Workflow settings live under `workflow.*` as in the reference
   (`workflow.business_timezone`, `workflow.stale_submission_after`, `workflow.max_submission_attempts`,
   `workflow.require_local_mandate`, `workflow.predeclare_before_declaring`, `workflow.erasure.*`); gateway settings
   at the root next to `environment` and `base_uri` (`retry_safe_writes`, `rate_limiter.*`).
5. **Hidden prompts.** Employer and employee birth dates and the NIR are read from a hidden prompt, never from command
   arguments (shell history, process list, cron logs); non-interactive use of a command that needs them exits with 2.
   Payloads of `estimate`, `predeclare` and `declare` are read from a JSON file path (`--payload`), never inline.
6. **No URSSAF description printed.** Commands print codes and package messages only.
7. **Exit codes.** `2` invalid usage, `1` failure, `0` otherwise.
8. **Simulator guard.** `gateway.simulated` is refused with `environment: production` at build time and at runtime.
9. **Diagnostics.** `urssaf:paje:check` (configuration, credentials, token, persistence, schema) and
   `urssaf:paje:status` (workflow overview).
10. **Defaults.** Safety crons: employers sync daily (020 is the only polling and is cheap but not free), stale
    declarations every 15 minutes, outbox relay every minute, outbox purge daily; rate limiter off (ADR 0011).

## Consequences

- Documented in `docs/symfony.md` and `docs/testing.md`.
