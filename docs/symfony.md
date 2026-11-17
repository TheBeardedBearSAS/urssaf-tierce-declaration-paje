# Symfony bundle

Bundle class: `TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\UrssafTierceDeclarationPajeBundle` (alias
`urssaf_tierce_declaration_paje`), for Symfony 6.4 and 7.x. Register it in `config/bundles.php`. The design decisions
that differ from the other URSSAF packages are in [ADR 0013](adr/0013-symfony-bridge.md): the names carry `paje`
(`urssaf:paje:` commands, `urssaf_paje` lock prefix, Monolog channel and schedule, `URSSAF_PAJE_*` variables), so
several URSSAF packages can be installed in the same application.

```php
// config/bundles.php
return [
    // ...
    TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\UrssafTierceDeclarationPajeBundle::class => ['all' => true],
];
```

```yaml
# config/packages/urssaf_tierce_declaration_paje.yaml
urssaf_tierce_declaration_paje:
    environment: '%env(URSSAF_PAJE_ENV)%'          # sandbox | production
    credentials:
        tenant-1:
            client_id: '%env(URSSAF_PAJE_CLIENT_ID)%'
            client_secret: '%env(URSSAF_PAJE_CLIENT_SECRET)%'
    persistence:
        driver: custom                              # in_memory (tests, prototypes) | custom, see Persistence
        employer_account_repository: app.employer_account_repository
        mandate_repository: app.mandate_repository
        declaration_record_repository: app.declaration_record_repository
        transaction_manager: app.transaction_manager
```

`symfony/symfony` is not required: the bundle needs `symfony/dependency-injection`, `config`, `http-kernel`, `console`,
`http-client` and `clock`; `symfony/lock`, `messenger`, `scheduler`, `validator`, `translation` and `cache` are optional
(`composer suggest`).

## Configuration reference

The configuration tree is defined in `Bridge\Symfony\DependencyInjection\BundleConfiguration`.

| Key | Type | Default | Description |
|---|---|---|---|
| `environment` | enum `sandbox` \| `production` | required | Target platform; usually `%env(URSSAF_PAJE_ENV)%`, resolved at runtime. |
| `base_uri` | string \| null | `null` | Overrides the base URI of the selected environment (sandbox host, proxy); validated at runtime. |
| `user_agent` | string | package default | `User-Agent` header of every call (visible ASCII). |
| `request_id_header` | string \| null | `null` | Name of the optional request-id header; its value is a new UUID for every attempt. Null: not sent ([ADR 0002](adr/0002-http-client-adaptations.md)). |
| `application_id_header` | `{name, value}` | `{name: null, value: null}` | Optional fixed header sent on every call. Both `name` and `value`, or neither. |
| `token_safety_margin` | int >= 0 | `60` | Seconds before expiry from which an access token is renewed. |
| `retry_safe_writes` | list of `"023"` \| `"030"` \| `"031"` | `[]` | Writes of unspecified idempotency that the sandbox showed idempotent: retried like a read after a transport error or a 5xx ([ADR 0003](adr/0003-retry-and-unknown-outcome.md)). **Quote the values in YAML**: an unquoted `023` is read as an octal integer. |
| `http_client` | string \| null | `null` | Service id of a PSR-18 client. Default: a dedicated Symfony HttpClient wrapped in `Psr18Client`. |
| `http_timeout` | float >= 0.1 | `15.0` | Timeout in seconds of the default HTTP client. With your own `http_client`, state its timeout here: it is checked against `workflow.stale_submission_after`. |
| `credentials_provider` | string \| null | `null` | Service id of a `CredentialsProvider`. Mutually exclusive with `credentials`. |
| `credentials` | map tenant id => `{client_id, client_secret}` | `[]` | Static credentials by tenant ([ADR 0010](adr/0010-tenant-model.md)); both keys required, non-empty. Use `%env(...)%` placeholders: the container dump only shows the placeholder. |
| `tenants` | list of string | keys of `credentials` | Tenants processed by the commands that cannot discover them. |
| `token_store.cache_pool` | string | `cache.app` | PSR-6 pool that stores the access tokens. Recommended: a **dedicated pool** (see below). |
| `token_store.service` | string \| null | `null` | Service id of a `TokenStore`; takes precedence over the cache pool. |
| `gateway.simulated` | bool | `false` | Tests only: replaces the URSSAF API with the in-memory `Testing\SimulatedPajeGateway`, exposed as a public service to script the scenarios (see [Simulator](#simulator-in-tests)). Refused with `environment: production`. |
| `rate_limiter.enabled` | bool | `false` | Throttles the calls of a tenant in the current process (`SlidingWindowRateLimiter`, [ADR 0011](adr/0011-quotas-and-rate-limit.md)). Off by default: URSSAF publishes no quota, so no value is invented. |
| `rate_limiter.limit` | int >= 1 | none | Calls per tenant within the window. Required with `rate_limiter.enabled`. |
| `rate_limiter.window` | int >= 1 | none | Window length in seconds. Required with `rate_limiter.enabled`. |
| `lock.enabled` | `auto` \| bool | `auto` | Non-blocking lock per command / handler (see [Locks](#locks-against-overlapping-runs)). `auto`: enabled when `symfony/lock` is installed and the factory service exists; `true`: required; `false`: disabled. |
| `lock.factory` | string | `lock.factory` | Service id of a `Symfony\Component\Lock\LockFactory`. |
| `lock.ttl` | float >= 1 | `3600.0` | Seconds after which the lock of a crashed process expires. Set it above the longest run of a command. |
| `outbox.enabled` | bool | `false` | Writes the integration events in an outbox, in the transaction of each state change (see [integration-events.md](integration-events.md)). |
| `outbox.service` | string \| null | `null` | Service id of your `Outbox` (required with the `custom` driver). Default: the in-memory outbox. |
| `outbox.sink` | string | `messenger` | `messenger`, `event_dispatcher`, or the service id of an `IntegrationEventSink`. |
| `outbox.bus` | string | `messenger.default_bus` | Bus of the `messenger` sink. |
| `outbox.retention` | ISO 8601 duration | `P30D` | How long a dispatched entry is kept (`urssaf:paje:outbox:purge`). |
| `scheduler.enabled` | bool | `false` | Registers a Symfony Scheduler schedule that runs the safety crons of the package (see [Scheduler](#scheduler)). Requires `symfony/scheduler`. |
| `scheduler.name` | string | `urssaf_paje` | Name of the schedule (transport `scheduler_<name>`). |
| `scheduler.frequencies.sync_employer_statuses` | ISO 8601 duration \| null | `P1D` | `urssaf:paje:employers:sync-status`; null leaves the task out. |
| `scheduler.frequencies.reconcile_stale_declarations` | ISO 8601 duration \| null | `PT15M` | `urssaf:paje:declarations:reconcile-stale`. |
| `scheduler.frequencies.relay_outbox` | ISO 8601 duration \| null | `PT1M` | `urssaf:paje:outbox:relay` (only with the outbox). |
| `scheduler.frequencies.purge_outbox` | ISO 8601 duration \| null | `P1D` | `urssaf:paje:outbox:purge` (only with the outbox). |
| `workflow.business_timezone` | string | `Europe/Paris` | "Today" of the period checks ([ADR 0001](adr/0001-date-only-encoding.md)); feeds the API configuration and the workflow policy. |
| `workflow.stale_submission_after` | ISO 8601 duration | `PT15M` | Age after which a `submitting` declaration is stale ([ADR 0003](adr/0003-retry-and-unknown-outcome.md), [ADR 0006](adr/0006-local-lifecycles.md)). Must be longer than `http_timeout`. |
| `workflow.max_submission_attempts` | int >= 1 | `5` | Attempts after which a technical failure is no longer requeued. |
| `workflow.require_local_mandate` | bool | `false` | Refuses a declaration locally when no active mandate is tracked ([ADR 0009](adr/0009-guards-and-opt-outs.md)). |
| `workflow.predeclare_before_declaring` | bool | `false` | Runs the pre-declaration check (045 / 046) before every declaration ([ADR 0008](adr/0008-results-and-predeclaration-check.md)): one more call per declaration. |
| `workflow.default_page_size` | int >= 1 | `100` | Records loaded by a batch use case when no limit is given. |
| `workflow.max_page_size` | int >= 1 | `1000` | Upper bound of the records loaded by a batch use case. |
| `workflow.erasure.allow_unknown_outcomes` | bool | `false` | Default of `ForgetEmployer` for declarations whose outcome is unknown: refused when `false` ([ADR 0015](adr/0015-employer-erasure.md)). A submission in flight is always refused. |
| `workflow.erasure.allow_active_mandate` | bool | `false` | Default of `ForgetEmployer` for an employer whose mandate is still active (it stays active at URSSAF): refused when `false`. |
| `persistence.driver` | enum `in_memory` \| `custom` \| `doctrine` | `doctrine` if `doctrine/orm` is installed, else `in_memory` | Persistence adapter. `doctrine` needs DoctrineBundle (the container build fails without it) and the schema is yours ([doctrine.md](doctrine.md)); `in_memory` is for tests and prototypes. |
| `persistence.employer_account_repository` | string \| null | `null` | Service id of your `EmployerAccountRepository` (driver `custom`). |
| `persistence.mandate_repository` | string \| null | `null` | Service id of your `MandateRepository` (driver `custom`). |
| `persistence.declaration_record_repository` | string \| null | `null` | Service id of your `DeclarationRecordRepository` (driver `custom`). |
| `persistence.transaction_manager` | string \| null | `null` | Service id of your `TransactionManager`, shared with your repositories (driver `custom`; default `ImmediateTransactionManager`). |
| `persistence.entity_manager` | string | `default` | Name of the Doctrine entity manager (driver `doctrine`). Use a dedicated one ([doctrine.md](doctrine.md)): the package mapping is attached to it only. |
| `persistence.workflow_statistics` | string \| null | `null` | Service id of your `WorkflowStatistics` (driver `custom`, optional: without it `GetWorkflowOverviewHandler` and `urssaf:paje:status` are not registered). |
| `messenger.enabled` | bool | `false` | Registers the Messenger handlers of [Messenger](#messenger). |
| `messenger.bus` | string | `messenger.default_bus` | Bus used by `urssaf:paje:employers:sync-status --async`. |

Validation rules enforced at container build time:

- `credentials` and `credentials_provider` are mutually exclusive, and one of them is required.
- `gateway.simulated` cannot be used with `environment: production` (a placeholder that resolves to `production` at
  runtime is refused when the gateway is built).
- `workflow.stale_submission_after` must be strictly longer than `http_timeout`: a threshold that does not exceed the
  timeout would let `ReconcileStaleDeclarations` reconcile a call that is still in flight
  ([ADR 0003](adr/0003-retry-and-unknown-outcome.md)). Durations are measured from 2026-01-01 UTC, so `P1M` counts 31
  days. The rule is skipped when `http_timeout` is an `%env(float:...)%` placeholder. The token call is not added to the
  timeout (a Symfony `timeout` is an idle timeout, not a total duration), so keep a wide margin: the default threshold is
  15 minutes for a 15-second timeout.
- `rate_limiter.limit` and `rate_limiter.window` are required with `rate_limiter.enabled`.
- `outbox.enabled` with the `custom` driver requires `outbox.service`; the `custom` driver requires the three
  repository service ids.
- `lock.enabled: true` requires `symfony/lock`; `scheduler.enabled: true` requires `symfony/scheduler`.
- `workflow.default_page_size` must not exceed `workflow.max_page_size`.
- `persistence.driver: doctrine` (including when it is the default) needs DoctrineBundle to be registered: the container build fails with a `LogicException` otherwise.
- `request_id_header` and `application_id_header.name` must be different headers; `application_id_header` needs both
  `name` and `value`, or neither.
- `retry_safe_writes` only accepts `"023"`, `"030"` and `"031"`.
- `workflow.business_timezone` must be a known time zone; `outbox.retention`, `workflow.stale_submission_after` and the
  scheduler frequencies valid ISO 8601 durations; `environment` a known value.

`base_uri`, the header names and values and `token_safety_margin` are validated when the configuration is built at
runtime (they may be environment placeholders): `urssaf:paje:check` reports them.

### Dedicated cache pool for the access tokens

The access tokens are stored in the `token_store.cache_pool` pool (`cache.app` by default). Declare a dedicated pool, so
that `cache:pool:clear cache.app` (a routine deployment step) does not force a new token request for every tenant, and
so that the tokens live in an adapter you control (Redis with authentication, for instance):

```yaml
framework:
    cache:
        pools:
            urssaf_paje.token_cache:
                adapter: cache.adapter.redis
                provider: '%env(REDIS_DSN)%'

urssaf_tierce_declaration_paje:
    token_store: { cache_pool: urssaf_paje.token_cache }
```

The pool must be shared by every process that calls URSSAF (web, workers, cron): a per-process `array` adapter would
request a token per run. Tokens are secrets: do not expose the pool through a cache dashboard or a shared namespace.

## Persistence

- `in_memory`: the three repositories, the statistics and an immediate transaction manager, in memory. Every record is
  lost when the process ends: tests and prototypes only.
- `custom`: your own implementations, as service ids: `employer_account_repository`, `mandate_repository` and
  `declaration_record_repository`. Give the `transaction_manager` they share: with the outbox, the integration events are
  written in the transaction of the state change ([ADR 0012](adr/0012-integration-events.md)), which an
  `ImmediateTransactionManager` (the default) cannot guarantee. `workflow_statistics` is optional.
- `doctrine`: the Doctrine bridge ([doctrine.md](doctrine.md), [ADR 0014](adr/0014-doctrine-bridge.md)). The bundle prepends the
  mapping and the DBAL types to the `doctrine` configuration (to the entity manager named by `entity_manager` only, when it is
  not `default`), and registers the repositories, the transaction manager, the statistics and, when the outbox is enabled
  without `outbox.service`, the `DoctrineOutbox`, all on that entity manager.

## Console commands

Every command is in the `urssaf:paje:` namespace (`bin/console list urssaf:paje`, then `--help` of each command for its
options). The operator never types a personal value on the command line: the employer and employee birth dates and the
NIR are read from a **hidden prompt** (not echoed, run the command interactively; a non-interactive run that needs one
exits with `2`), the structured personal inputs are read from a JSON file given by path, never inline: `--payload` for `estimate`,
`predeclare`, `declare` and `children:verify`, `--identity-payload` for `employee:verify` and `employee:associate`, and
`--creation-payload` for `employee:associate`. A file is at most 1 MiB and holds a JSON object or list; it is read, and an
invalid usage refused, before any prompt; its content is never echoed; an unreadable or invalid file exits with `2`. The commands print identifiers, states, codes, amounts and instants only
([ADR 0007](adr/0007-personal-data.md)). **URSSAF error descriptions are not printed**: a rejection is shown by its
codes and the message of the package.

| Command | Purpose |
|---|---|
| `urssaf:paje:employer:verify` | Verify an employer (method 020); writes nothing. |
| `urssaf:paje:employee:verify` | Verify an employee (method 021); writes nothing. |
| `urssaf:paje:children:verify` | Check the entitlement of the children of an employer (method 022); writes nothing. |
| `urssaf:paje:employee:associate` | Associate an employee to an employer, creating it when needed (method 023). |
| `urssaf:paje:employer:track` | Track an employer after verifying it with URSSAF (method 020). |
| `urssaf:paje:employer:import` | Track an employer already known to URSSAF (no URSSAF call). |
| `urssaf:paje:mandate:register` | Notify the mandate of a tracked employer (method 030). |
| `urssaf:paje:mandate:cancel` | Cancel the mandate (method 031). |
| `urssaf:paje:mandate:import`, `urssaf:paje:mandate:record-cancellation` | Record a mandate, or its cancellation, obtained outside the package (no URSSAF call). |
| `urssaf:paje:estimate` | Estimate the contributions of a salary (methods 040 / 041); `--payload`. |
| `urssaf:paje:predeclare` | Check a declaration without declaring it (methods 045 / 046); `--payload`. |
| `urssaf:paje:declare` | Declare a salary (methods 050 / 051); `--payload`. |
| `urssaf:paje:declaration:show`, `urssaf:paje:declarations:list` | Show or list the local declaration records (no URSSAF call). |
| `urssaf:paje:declaration:resolve` | Settle an `outcome_unknown` declaration after a manual check ([ADR 0003](adr/0003-retry-and-unknown-outcome.md)). |
| `urssaf:paje:declarations:requeue-invalid-credentials` | Move the declarations refused for invalid credentials back to `pending`, once the credentials are fixed. |
| `urssaf:paje:employers:sync-status` | Refresh the tracked employers, least recently checked first (method 020); `--async` dispatches one message per tenant; with an employer selector, `--tenant` defaults to the only configured tenant. |
| `urssaf:paje:declarations:reconcile-stale` | Move the declarations left `submitting` for `workflow.stale_submission_after` to `outcome_unknown`: they are never resent. |
| `urssaf:paje:employer:forget` | Erase a tracked employer, its mandates and its declaration records from the local store (GDPR, [ADR 0015](adr/0015-employer-erasure.md)); nothing is sent to URSSAF. |
| `urssaf:paje:outbox:relay`, `urssaf:paje:outbox:purge` | Deliver the pending integration events, delete the old dispatched ones. Only with the outbox. |
| `urssaf:paje:status` | Count the employers, mandates and declarations by tenant and state (local store only); `--format=json` for monitoring. Not available with a `custom` driver without `workflow_statistics`. |
| `urssaf:paje:check` | Check the installation (see below); `--skip-remote` does not call URSSAF. |

| Exit code | When |
|---|---|
| `0` | Done (whatever the remote answer of a read); lock held (skipped); `--dry-run`. |
| `1` | A package exception stops the command (URSSAF refusal, authentication failure, unavailability, unknown outcome, record not found, erasure refused...); a batch item ended `failed`, `invalid_credentials` or `manual_review`; a failed delivery or check. |
| `2` | Invalid usage: missing, conflicting or malformed option, no tenant, input rejected by validation, a hidden value or a confirmation requested in non-interactive mode. |

Nothing in the console submits a declaration by itself: `urssaf:paje:declare` is run by an operator, and no scheduled
task declares ([ADR 0013](adr/0013-symfony-bridge.md), decision 2).

### Safety crons

The only polling of the package is method 020 (cheap, not free), hence a daily default; the stale declarations are
looked at every 15 minutes (a record left `submitting` after a crash is never resent, it is moved to manual review,
[workflow.md](workflow.md)). A typical crontab (or the [Scheduler](#scheduler), whose defaults are the same):

```cron
0 3 * * *      php bin/console urssaf:paje:employers:sync-status
*/15 * * * *   php bin/console urssaf:paje:declarations:reconcile-stale
* * * * *      php bin/console urssaf:paje:outbox:relay
0 4 * * *      php bin/console urssaf:paje:outbox:purge
```

Without the outbox, leave the last two out.

### Checking the installation

`urssaf:paje:check` prints one line per check and exits with `1` when one of them fails (warnings do not fail): run it
after each deployment, or in the CI of the application with `--skip-remote`. `urssaf:paje:status` is the monitoring
view: it reads the local store only and never calls URSSAF.

| Check | Failure / warning |
|---|---|
| `configuration` | failure: the configuration cannot be built (unknown `URSSAF_PAJE_ENV`, invalid base URI, header or safety margin) |
| `credentials` | failure: no credentials for a tenant in the configured provider |
| `authentication` | failure: URSSAF refuses the credentials or cannot be reached. A new access token is requested for every tenant (any cached one is ignored), the tokens of the application are not touched. Skipped with `--skip-remote`; replaced by a `gateway` warning when `gateway.simulated` is enabled |
| `token store` | warning: the tokens are stored in `cache.app` instead of a dedicated pool |
| `persistence` | warning: `in_memory` persistence (every record is lost when the process ends) |
| `database schema` | `doctrine` driver only. Failure: schema changes are pending on the package tables (run `doctrine:migrations:diff` then `doctrine:migrations:migrate`). Warning: the `default` entity manager is shared with the application |
| `lock` | warning: the lock is disabled or `symfony/lock` is missing |

Add checks of your own by
implementing `Bridge\Symfony\Diagnostic\InstallationCheck` (autoconfigured, tag
`urssaf_tierce_declaration_paje.installation_check`): they run after the package checks, unless their tag has a positive
`priority`.

## Messenger

With `messenger.enabled: true`, the following messages (namespace `Bridge\Symfony\Message`) are handled by the handlers
of `Bridge\Symfony\MessageHandler`. They are plain `final readonly` classes holding identifiers only, so they serialize
with any Messenger serializer and carry no personal data ([ADR 0007](adr/0007-personal-data.md)).

| Message | Properties | Effect |
|---|---|---|
| `SyncEmployerStatusesMessage` | `tenantId` | Refreshes the least recently checked employers of the tenant (method 020). |
| `SyncEmployerStatusMessage` | `tenantId`, `employerAccountId` | Refreshes one tracked employer, e.g. dispatched with a `DelayStamp` after the mandate notification (030). |

Route them to a transport in your `framework.messenger.routing` (for instance
`'TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Message\*': async`).

There is **no declaration message**: `DeclareSalary` is synchronous and the record never holds the payload
([ADR 0006](adr/0006-local-lifecycles.md), [ADR 0007](adr/0007-personal-data.md)); no 023, 030 or 031 message either (an
answer is needed, and these writes are never resent after an unknown outcome, [ADR 0003](adr/0003-retry-and-unknown-outcome.md)).
Wrap the handler in a message of your own if you want one of them asynchronous. `urssaf:paje:employers:sync-status
--async` dispatches one `SyncEmployerStatusesMessage` per tenant on `messenger.bus`.

Failure mapping (`MessageFailures`):

- `RetryableException` (URSSAF unavailable, rate limit exceeded...) => `RecoverableMessageHandlingException`: the
  message is retried by the retry strategy of the transport, with the delay requested by URSSAF (`Retry-After`) or by
  the local rate limiter when known (on symfony/messenger older than 7.2 the exception carries no delay).
- Operation already running (lock held) => `RecoverableMessageHandlingException` with a 30 s delay.
- Any other `UrssafException` (unknown account, authentication failure, rejection, unknown outcome) =>
  `UnrecoverableMessageHandlingException`: retrying the same message cannot succeed.
- Failures of other origins (database down...) are left to the retry strategy of the host.

The batch handler (`SyncEmployerStatusesMessage`) absorbs the temporary failure of one account (the account is left
untouched and retried by the next run); it only fails for refused credentials, which concern the whole tenant.

## Scheduler

Instead of the crontab, the bundle can register a [Symfony Scheduler](https://symfony.com/doc/current/scheduler.html)
schedule that runs the same console commands, through Messenger (`Symfony\Component\Console\Messenger\RunCommandMessage`,
handled by the framework): same locks, same reports, same safety nets.

```bash
composer require symfony/scheduler   # 6.4.3 or later (the package conflicts with older releases)
```

```yaml
urssaf_tierce_declaration_paje:
    scheduler:
        enabled: true
        frequencies: { sync_employer_statuses: PT6H, reconcile_stale_declarations: PT15M }
```

Run one worker for the schedule (and keep it running, like any Messenger worker):

```bash
php bin/console messenger:consume scheduler_urssaf_paje
```

`urssaf:paje:declarations:requeue-invalid-credentials` is not scheduled: run it by hand after fixing the credentials. A
command that exits with a non-zero code makes its message fail (`RunCommandFailedException`). Run a single scheduler
worker (or enable the lock): two workers of the same schedule would run each task twice. The outbox tasks are left out
when the outbox is disabled.

An exit code 1 used as an alert (`reconcile-stale` moved a record to manual review, an item of `sync-status` failed)
shows up as a failed, then retried, `RunCommandMessage`: monitor those failures, or set the retry strategy of the
scheduler transport (`max_retries: 0`) so that the message is not run again:

```yaml
framework:
    messenger:
        transports:
            scheduler_urssaf_paje:
                dsn: 'scheduler://urssaf_paje'
                retry_strategy: { max_retries: 0 }
```

## Locks against overlapping runs

Two crons that overlap, a cron racing a Messenger worker, or two workers handling the same tenant could poll the same
records at the same time. The atomic claims keep that safe but wasteful, and the quota is shared. When `symfony/lock` is
installed, the batch commands, the mandate commands and the Messenger handlers take a **non-blocking** lock named
`urssaf_paje.<operation>.<scope>` before they work, and release it afterwards:

| Operation | Used by | Scope |
|---|---|---|
| `employers.sync-status` | `urssaf:paje:employers:sync-status` (batch), `SyncEmployerStatusesMessage` | tenant (one lock per tenant processed) |
| `employer.sync-status` | `urssaf:paje:employers:sync-status` with an employer selector, `SyncEmployerStatusMessage` | account id |
| `declarations.reconcile-stale` | `urssaf:paje:declarations:reconcile-stale` | `*` |
| `requeue-invalid-credentials` | `urssaf:paje:declarations:requeue-invalid-credentials` | tenant |
| `mandate` | the mandate and erasure commands of one employer | account id |
| `outbox.relay` | `urssaf:paje:outbox:relay` | `*` |

The interactive read commands (verify, estimate, predeclare) and `declare` take no lock: the domain uniqueness rules and
the atomic claim of the record protect them.

When the lock is already held: a **command** prints that the operation is already running and exits with `0`; a
**Messenger handler** throws `RecoverableMessageHandlingException` with a 30 s delay. `--dry-run` takes no lock.

```bash
composer require symfony/lock
```

```yaml
framework:
    lock: '%env(LOCK_DSN)%'   # redis://..., or `flock`; must be shared by every host that runs the crons / workers

urssaf_tierce_declaration_paje:
    lock: { enabled: auto, factory: lock.factory, ttl: 3600 }
```

The store must be visible to all the processes that may overlap: `flock` only protects processes of the same machine.
`ttl` bounds how long the lock of a crashed process blocks the operation. Without `symfony/lock` (or with
`enabled: false`) everything just runs, protected by the claims only.

## Simulator in tests

With `gateway.simulated: true` (typically in `config/packages/test/`), the URSSAF API is replaced by the in-memory
`Testing\SimulatedPajeGateway`, a public service you script from your tests; nothing is sent to URSSAF. The bundle refuses it with `environment: production`: at build time for a literal value, and
when the gateway is built for an `%env()%` placeholder that resolves to `production`. `urssaf:paje:check` reports a
`gateway` warning instead of the authentication check.

## Validation and forms

The use cases validate their input themselves and throw `ValidationFailed` with every violation at once (code
`<subject>.<reason>`, path such as `children[0].birth_date`, scalar params). When `symfony/validator` is installed, the
bundle registers the public service `Bridge\Symfony\Validation\ViolationListConverter`, which turns them into a Symfony
`ConstraintViolationList`:

| `ConstraintViolation` | Value |
|---|---|
| `getPropertyPath()` | the violation path, with the optional prefix |
| `getCode()`, `getMessageTemplate()` | the violation code (`period_start.required`) |
| `getMessage()` | the translation of the code in the domain `urssaf_tierce_declaration_paje` (current locale of the `translator`), or the English message without translator |
| `getParameters()` | the violation params as `%name%` placeholders |
| `getCause()` | the original `Domain\Validation\Violation` |
| `getInvalidValue()` | always `null`: the rejected raw values (personal data) are never exposed |

`convert()` keeps the errors only; `convertWithWarnings()` keeps the warnings too. With a form, map each violation on the
form field of its path; an API controller can wrap the list in a `ValidationFailedException`.

## Translations

The violation codes (`<subject>.<reason>`, for example `nir.too_short`) are translatable messages of the translation
domain `urssaf_tierce_declaration_paje`. The bundle ships English and French:
`Resources/translations/urssaf_tierce_declaration_paje.en.yaml` and `urssaf_tierce_declaration_paje.fr.yaml`. They cover
**every** violation code of the package, the technical ones included, with the same keys in the same order: a test reads
the sources, finds the codes and fails when one has no message in a locale (or a message has no code).

To add another locale: create `translations/urssaf_tierce_declaration_paje.<locale>.yaml` in your application, with the
same keys as the English file. To reword a shipped message, define the key in your application's file of that locale:

```yaml
# translations/urssaf_tierce_declaration_paje.fr.yaml
"nir.too_short": "Le NIR est trop court : 15 caractères attendus."
```

Keys defined in your application's `translations/` directory override the shipped messages. Translate a violation with
`$translator->trans($violation->code(), $params, 'urssaf_tierce_declaration_paje')`, where `$params` maps each violation
parameter to a `%name%` key (`['%max%' => '15']`): `ViolationListConverter` does it for you and is the simplest way to
get translated messages.

## Doctrine

The `doctrine` driver ([ADR 0014](adr/0014-doctrine-bridge.md), full guide in [doctrine.md](doctrine.md)) is the default when
`doctrine/orm` is installed. It requires DoctrineBundle; the package ships no migration (generate it with
`doctrine:migrations:diff`, `urssaf:paje:check` reports the pending changes).

```yaml
doctrine:
    dbal:
        connections:
            default: { url: '%env(resolve:DATABASE_URL)%' }
            urssaf_paje: { url: '%env(resolve:URSSAF_PAJE_DATABASE_URL)%' }
    orm:
        entity_managers:
            default: { connection: default }
            urssaf_paje: { connection: urssaf_paje }   # dedicated: the bundle maps the package entities here

urssaf_tierce_declaration_paje:
    persistence: { driver: doctrine, entity_manager: urssaf_paje }
    outbox: { enabled: true }                           # DoctrineOutbox on the same entity manager
```

The repositories flush the whole unit of work of the entity manager they use on every save, so a dedicated entity manager
is strongly recommended. A lost optimistic lock is reported as `employer_account.concurrent_modification`,
`mandate.concurrent_modification` or `declaration_record.concurrent_modification` (translated with the other violation
codes).
