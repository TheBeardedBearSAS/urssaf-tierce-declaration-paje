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

- Documented in `docs/symfony.md` and `docs/standalone.md`.

## Amendment (2026-11-17, lead-accepted, lot 4b)

- **Simulator guard without a framework:** the layer rules forbid `Standalone` to depend on `Testing`, so the standalone
  builder cannot recognise `Testing\SimulatedPajeGateway`. `UrssafTierceDeclarationPajeBuilder::withGateway()` therefore refuses
  **any** custom `PajeGateway` with `Environment::Production` (`LogicException`, at the call, so before any use); a host that
  needs a decorated gateway in production wires the handlers itself (docs/standalone.md, manual wiring). The bundle guard
  above (item 8) is unchanged.
- **Logger:** the standalone `withLogger()` reaches the HTTP gateway only: no `FailureReporter` backed by a PSR logger exists yet,
  so a failure of the event publication after a commit is ignored unless the host's `EventPublisher` reports it.

## Amendment (2026-11-17, lead-accepted, lot 5)

- **Doctrine driver refused for now.** The Doctrine bridge ships in a later lot (ADR 0014), so `persistence.driver: doctrine` is
  refused at container build (a rule of the configuration root, which also catches the default: `doctrine` is still the default
  when `doctrine/orm` is installed, so a host with the ORM must choose `in_memory` or `custom` explicitly until then). The
  options `persistence.entity_manager`, `ServiceIds::entityManager()` and the Doctrine prepend arrive with that bridge.
- **Stale threshold versus HTTP timeout (ADR 0003, ADR 0006).** `workflow.stale_submission_after` must be strictly longer than
  `http_timeout` (a build-time rule; the interval is measured from 2026-01-01 UTC, so `P1M` counts 31 days). A threshold that
  does not exceed the timeout would let `ReconcileStaleDeclarations` reconcile a call still in flight. The rule is skipped when
  `http_timeout` is an `%env(float:...)%` placeholder (only known at runtime). The margin for the token call is not added: the
  Symfony `timeout` option is an idle timeout, not a total duration, so no exact bound exists; the default threshold (15 minutes)
  leaves ample room for the default timeout (15 seconds). With a custom `http_client`, `http_timeout` must state the timeout of that
  client for the rule to mean something.
- **Configuration tree.** Root: `environment`, `base_uri`, `user_agent`, `request_id_header`, `application_id_header`,
  `token_safety_margin`, `retry_safe_writes` (list of `"023"`, `"030"`, `"031"`, quoted in YAML, ADR 0003), `http_client`,
  `http_timeout`, `credentials` / `credentials_provider`, `tenants`, `token_store`, `gateway.simulated`, `rate_limiter`, `lock`,
  `outbox`, `scheduler`, `messenger`, `persistence`, `workflow`. `workflow.business_timezone` also feeds the API configuration.
  `workflow.require_local_mandate` and `workflow.predeclare_before_declaring` default to `false` (ADR 0009, ADR 0008).
  `rate_limiter.limit` and `rate_limiter.window` have no default and are required with `rate_limiter.enabled: true` (ADR 0011:
  no value is invented). The employer synchronisation schedule defaults to `P1D` (decision 10).
- **Infrastructure added for the bundle.** `Infrastructure\Event\Psr14EventPublisher`, `Infrastructure\Event\Psr14IntegrationEventSink`
  and `Infrastructure\Logging\PsrLoggerFailureReporter` (as in the sibling packages) and the `psr/event-dispatcher` requirement;
  `symfony/scheduler` joins `require-dev` and `suggest`, with its conflict floor `<6.4.3` (ADR 0000, item 2).
- **Secrets.** Credentials are configured per tenant; the container dump only ever shows the `%env()%` placeholder of a secret
  (tested). A literal secret is not rejected by the tree: a rule on a placeholder is fragile, see the open question of the lot.

## Amendment (2026-11-17, lead-accepted, lot 5, Messenger, scheduler, diagnostics and translations)

- **Locks.** Operations take a non-blocking lock named `urssaf_paje.<operation>.<scope>` (`OperationLocks`). The Messenger
  handlers use `employers.sync-status` (scope: tenant) and `employer.sync-status` (scope: account id), the same names as the
  commands, so a cron and a worker of the same scope exclude each other. A held lock is a recoverable failure with a 30 s
  delay for a message, a skip with exit code `0` for a command.
- **Failure mapping.** `RetryableException` (URSSAF unavailable, local rate limit) is recoverable, with the delay of
  `Retry-After` or of the limiter when known (symfony/messenger 7.2 and later); every other `UrssafException`, `OutcomeUnknown`
  included, is unrecoverable. The batch handler absorbs the temporary failure of one account (the use case reports it as
  `retry_later`) and fails only for refused credentials.
- **Scheduler.** `UrssafScheduleProvider` runs `RunCommandMessage` for four tasks: `sync_employer_statuses`
  (`urssaf:paje:employers:sync-status`), `reconcile_stale_declarations` (`urssaf:paje:declarations:reconcile-stale`),
  `relay_outbox` and `purge_outbox` (only with the outbox). The requeue of the declarations refused for invalid credentials
  and every command that declares are never scheduled. `symfony/scheduler` below 6.4.3 conflicts (ADR 0000, item 2).
- **Diagnostics.** `urssaf:paje:check` runs, in this order, the `configuration`, `credentials`, `authentication` (or `gateway`
  with the simulator), `token store`, `persistence` and `lock` checks, then those of the host (tag
  `urssaf_tierce_declaration_paje.installation_check`); only a failure exits with `1`. The database schema check arrives with
  the Doctrine bridge. `urssaf:paje:status` needs a statistics port (never with a `custom` driver without `workflow_statistics`).
- **Translations.** The domain `urssaf_tierce_declaration_paje` ships English and French messages for **every** violation code
  found in `src/` (technical codes included), in the same order; a test reads the sources and fails when a code has no message
  in a locale, or a message has no code. A second test feeds hostile values to every input factory and checks that each
  produced code is translated.

## Amendment (2026-11-17, lead-accepted, lot 5, console commands)

- **Commands** (`urssaf:paje:`): `employer:track` (020, then the account), `employer:import` (no call), `employer:verify` (020),
  `employer:forget` (local erasure, ADR 0015), `employers:sync-status`, `mandate:register` (030), `mandate:import`,
  `mandate:cancel` (031), `mandate:record-cancellation`, `employee:verify` (021), `employee:associate` (023), `children:verify`
  (022), `estimate` (040 / 041), `predeclare` (045 / 046), `declare` (050 / 051), `declarations:reconcile-stale` (alias
  `declarations:reconcile`), `declaration:resolve`, `declarations:requeue-invalid-credentials`, `declaration:show`,
  `declarations:list`, and, with the outbox only, `outbox:relay` and `outbox:purge`. `check` and `status` are the diagnostics.
  Every command that needs a tenant has `--tenant`, defaulting to the only configured tenant.
- **Personal inputs (item 5, extended).** Beyond the hidden prompts for the employer birth date (every command that sends an
  employer reference: `employer:track`, `employer:import`, `employer:verify`, `employee:associate`, `children:verify`,
  `predeclare`, `declare`), the NIR (`--nir`) and the employee birth date (`--employee-birth-date`), the inputs that hold names,
  birth dates, addresses or children come from JSON files read through the Domain input factories, never from the command line:
  `--payload` (`children:verify`: the list of children; `estimate`, `predeclare`, `declare`: the request),
  `--identity-payload` (`employee:verify`, `employee:associate`: the employee identity, instead of the number or the NIR) and
  `--creation-payload` (`employee:associate`: the data to create the employee). The activity is the option `--activity`
  (`childminder` or `home_care`), never a key of the file. A file that cannot be read, is larger than 1 MiB, is not JSON or is not
  an object or a list exits with `2`, without echoing any of its content. The files are read, and invalid usages refused, before
  any prompt. The Pajemploi numbers (employer, employee) are arguments or selectors: they never reach an output, a lock name or a
  confirmation question.
- **Outputs.** Identifiers, states, codes, amounts and instants only: never an employer or employee number, a name, a date of
  birth, a NIR, nor the message or the description of an URSSAF error (they are filled with host values, ADR 0007); the error
  codes and their kind are printed. Non-blocking messages are printed as codes.
- **Exit codes (item 7, detailed).** `declare` exits with `0` only when the record is `declared`; `rejected`, `already_declared`,
  `outcome_unknown`, `invalid_credentials`, an unclaimed record and a replay of any of those exit with `1` (they need an operator,
  and a hint says what to run). Batch commands exit with `1` when an item is `failed`, `invalid_credentials` or `manual_review`
  (`declarations:reconcile-stale` therefore alerts when it moved a record), and `outbox:relay` when a delivery failed. A busy lock
  is not a failure (`0`).
- **Recovery hints.** `mandate.recording_failed` (URSSAF accepted 030 or 031) says never to run the command again and points to
  `mandate:import` (030) or `mandate:record-cancellation` (031); `declaration_record.recording_failed` prints the document
  reference and points to `declarations:reconcile-stale` then `declaration:resolve --declared-as`; a retryable failure of
  `declare` says when to run it again.
- **Locks (ADR 0006).** The four mandate commands and `employer:forget` share the operation `mandate`; the scope is the id of the
  employer account, resolved from the selector first (`--employer`, `--employer-number` or `--account-id` give the same lock). The
  status refresh of one employer uses its own operation (`employer.sync-status`), the tenant run `employers.sync-status` per
  tenant, `declarations.reconcile-stale` and `outbox.relay` are global (`*`), `requeue-invalid-credentials` is per tenant.
  `declare` takes no lock: the claim of the record is atomic (ADR 0003).
- **Confirmation.** `mandate:cancel` and `employer:forget` ask for a confirmation (`--force` skips it, required when not
  interactive, exit `2`); the question does not name the employer.

## Amendment (2026-11-17, lead-accepted, lot 5, structure)

- The driving adapters are registered by `CommandRegistrar`, `MessengerRegistrar`, `SchedulerRegistrar` and
  `ValidationRegistrar` (plus `GatewayOptionsFactory`) instead of the single `EntryPointRegistrar` of the CESU package:
  each one is enabled by its own configuration node or installed component. `DatabaseSchemaCheck` arrives with the
  Doctrine bridge (lot 6).
- The Flex recipe of the reference package is not shipped: the repository skeleton has no recipe tooling nor
  `flex-endpoint` CI job. Configuration is documented in `docs/symfony.md`; a recipe is a later addition.
