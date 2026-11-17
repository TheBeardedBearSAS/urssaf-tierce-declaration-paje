# Without a framework

`TheBeardedBear\UrssafTierceDeclarationPaje\Standalone\UrssafTierceDeclarationPajeBuilder` builds every use case of the
package from a PSR-18 transport (`HttpTransport`), a `CredentialsProvider` and the `ApiConfiguration`; `build()`
returns `Standalone\UrssafTierceDeclarationPaje`, whose methods return the 25 handlers and the gateway (`gateway()`).
Each accessor returns a new, stateless handler. A handler is invoked with its command or query: `$urssaf->declareSalary()($command)`.

| Commands | Queries |
|---|---|
| `trackEmployer()`, `importEmployer()`, `syncEmployerStatus()`, `syncEmployerStatuses()`, `forgetEmployer()` | `getEmployerAccount()`, `verifyEmployer()` |
| `registerMandate()`, `importMandate()`, `cancelMandate()`, `recordMandateCancellation()` | `verifyEmployee()`, `verifyChildren()` |
| `associateEmployee()` | `estimateContributions()` |
| `declareSalary()`, `resolveDeclarationOutcome()`, `reconcileStaleDeclarations()`, `requeueAfterInvalidCredentials()` | `preDeclareSalary()`, `getDeclaration()`, `findDeclarations()` |
| `relayOutbox()`, `purgeOutbox()` | `getWorkflowOverview()` |

```php
$urssaf = UrssafTierceDeclarationPajeBuilder::create(
    new HttpTransport($psr18Client, $psr17Factory, $psr17Factory),
    new StaticCredentialsProvider(['tenant-1' => Credentials::of($clientId, $clientSecret)]),
    ApiConfiguration::forEnvironment(Environment::Sandbox),
)->build();
```

Every `with...()` method returns a new builder (the builder is never modified):

| Method | Default | Use |
|---|---|---|
| `withTokenStore(TokenStore)` | in-memory | A store shared by every process that calls URSSAF (`Psr6TokenStore`, `Psr16TokenStore`). |
| `withClock(Clock)` | UTC system clock | Also used by the HTTP gateway. |
| `withIdGenerator(IdGenerator)` | random UUIDs | Record ids and request ids. |
| `withEventPublisher(EventPublisher)` | none | Domain events after each commit. |
| `withLogger(LoggerInterface)` | none | Logs of the HTTP gateway (never personal data, [ADR 0007](adr/0007-personal-data.md)). |
| `withRateLimiter(RateLimiter)` | `SlidingWindowRateLimiter` | The HTTP gateway enforces the URSSAF quota in the process; `NoopRateLimiter` disables it ([ADR 0011](adr/0011-quotas-and-rate-limit.md)). |
| `withPolicy(WorkflowPolicy)` | defaults | Page sizes, stale submission delay (must exceed the HTTP client timeout), attempts, business time zone, mandate guard, pre-declaration check, erasure opt-ins, outbox retention. |
| `withRepositories($accounts, $mandates, $declarations, $transactions, ?$statistics)` | in-memory | Your own persistence; without statistics `getWorkflowOverview()` throws. |
| `withDoctrine(EntityManagerInterface)` | in-memory | The Doctrine persistence of the package on a **dedicated** entity manager ([doctrine.md](doctrine.md)): repositories, `DoctrineTransactionManager` and `DoctrineWorkflowStatistics`. The outbox is separate (below). |
| `withOutbox(Outbox, IntegrationEventSink)` | no outbox | Integration events ([integration-events.md](integration-events.md)); `relayOutbox()` needs it. |
| `withGateway(PajeGateway)` | HTTP gateway | For instance the `Testing\SimulatedPajeGateway` in tests. **Refused with `Environment::Production`** (`LogicException`): nothing but the HTTP gateway talks for a real employer. |

The defaults (in-memory repositories, statistics and token store) are meant for tests and prototypes: they live and die
with the process. In production, use `withDoctrine()` or your own repositories, and a token store shared by every process.

```php
$package = UrssafTierceDeclarationPajeBuilder::create($transport, $credentials, $configuration)
    ->withDoctrine($entityManager)                              // dedicated entity manager, schema created by your migrations
    ->withOutbox(new DoctrineOutbox($entityManager), $sink)     // optional: events written in the transaction of the state change
    ->build();
```

Register the types and the mapping first (`DoctrineIntegration::registerTypes()` and `DoctrineIntegration::mappingDriver()`,
see [doctrine.md](doctrine.md)); `withDoctrine()` does not touch the entity manager configuration.

## Declaring a salary

```php
$declaration = (new DeclarationFactory())->childminder($input);   // ValidationFailed lists every violation at once

$submission = $urssaf->declareSalary()(new DeclareSalary($tenant, $payslipReference, $employer, $declaration));

match ($submission->outcome) {
    ItemOutcome::Declared => $submission->declaration->status->result?->documentReference,
    ItemOutcome::AlreadyDeclared, ItemOutcome::OutcomeUnknown => 'an operator checks on pajemploi.urssaf.fr',
    default => null,
};
```

`$payslipReference` is your idempotency key (`ExternalReference`): see the re-entry table in
[workflow.md](workflow.md#re-entry-table). The outcomes and what to do with each are in
[workflow.md](workflow.md#outcome-table). `preDeclareSalary()` is the same dry run without any record (045 / 046).

## Scheduling

Run the polling and recovery use cases yourself (cron, worker), see [workflow.md](workflow.md#safety-crons):

- `syncEmployerStatuses()` per tenant (method 020) to follow the employer accounts;
- `reconcileStaleDeclarations()` to mark declarations stuck in `submitting` as `outcome_unknown`;
- `requeueAfterInvalidCredentials()` per tenant once the credentials are fixed, then send the requeued declarations
  again with `declareSalary()`: the package does not keep the payload ([ADR 0007](adr/0007-personal-data.md));
- `relayOutbox()` and `purgeOutbox()` when the outbox is used.

Two runs of the same use case must not overlap (use a lock of your own); the atomic claim keeps `declareSalary()` safe.

## Unknown outcomes

A declaration whose answer was lost is `outcome_unknown` and is never resent ([ADR 0003](adr/0003-retry-and-unknown-outcome.md)):
check it on pajemploi.urssaf.fr, then call `resolveDeclarationOutcome()` (`asDeclared` with the `referenceDocumentaire`, or
`asNotReceived`). See [workflow.md](workflow.md#unknown-outcomes-reconcile-and-resolve).

## Testing with the simulator

`Testing\SimulatedPajeGateway` is an in-memory URSSAF PAJE platform: it keeps the employers, the mandates, the employees,
the associations and the declarations, answers the twelve operations like the real API (same errors, classified by the
same `ErrorClassifier`) and never contacts URSSAF. It is refused by the builder with the production environment.

```php
$simulator = new SimulatedPajeGateway();
$simulator->knowEmployer($employer);                 // the employer exists (no mandate yet)
$urssaf = $builder->withGateway($simulator)->build();

$urssaf->registerMandate()(...);                     // 030 holds the mandate in the simulator
$simulator->documentReferences();                    // the declarations recorded, SIM-DECL-000001...
```

Default behaviour:

| Operation | Answer |
|---|---|
| 020 | unknown number `ER_API_020_0010`, other birth date `ER_API_020_0000`, else `Actif` and `authorised` = the calling tenant holds the mandate |
| 021 | a `knowEmployee()` employee (found by its number or any alias) with its account, otherwise the state `Absent` |
| 022 | `Entitled` for a child given to `entitleChild()`, `NotEntitled` otherwise |
| 023 | unknown employer `PE_INEXISTANT`; known employee associated (again: non-blocking `ER_API_023_NB_0000`); unknown employee created when creation data is given (`SIMSAL00000001`...), `PARAM_INVALIDE` otherwise |
| 030 / 031 | unknown number or other birth date `ER_API_020_0000`; mandate held by another tenant `COMPTE_DEJA_MANDATE` |
| 040 / 041 | `setEstimate()` (default employer share 63.10 EUR) |
| 045 / 046 | like 050 / 051, nothing recorded |
| 050 / 051 | unknown employer `ER_API_020_0010`; duplicate: `ER_API_050_0070` (childminder: same employee, period and child) or `ER_API_DECLA_00020` (home care: same employee and month); otherwise recorded with a new `SIM-DECL-000001` reference and the amounts of `setAmounts()` |

The specification does not say which operations need a mandate ([api-reference.md](api-reference.md) §5): by default none
does; `requireMandate()` makes the four declaration operations answer `MANDAT_INEXISTANT` to a tenant that does not hold it.
The association of an employee is not required before declaring (`ER_API_DECLA_00100`): script it with `refuseNext()`.

Script the failures, per operation, in order:

| Method | Effect |
|---|---|
| `failNext(Operation, Throwable)` | the call throws before anything is processed, e.g. `UrssafUnavailable::withStatus(...)`, `UrssafAuthenticationFailed` |
| `refuseNext(Operation, $code, $message, $description)` | the call is refused (HTTP 400) with this error, classified like the real API |
| `loseNextAnswer(Operation)` | the call is processed (state changed, reference consumed) but throws `OutcomeUnknown` (023, 030, 031, 050, 051 only) |

Inspection: `calls()` (every call received, failed ones included), `documentReferences()`, `isMandated()`.

## Manual wiring

The builder only calls public constructors: wire the classes yourself when you need another combination (a decorated
gateway in production, for instance).

```php
$gateway = UrssafGatewayFactory::create($transport, $credentials, $tokenStore, $configuration, GatewayOptions::defaults()->withClock($clock));
// $transactions is the host TransactionManager (a Doctrine or DBAL transaction around the operation). Do not use
// ImmediateTransactionManager here: it runs the operation without any transaction and is only meant for in-memory
// persistence (tests, demos), where there is nothing to roll back (see "Transactions" below).
$unitOfWork = new UnitOfWork($transactions, $eventPublisher, new IgnoredFailures(), $outbox);

$declareSalary = new DeclareSalaryHandler(
    $declarations,
    $accounts,
    new DeclarationSubmitter($declarations, $gateway, $gateway, $clock, $unitOfWork, $policy),
    new MandateGuard($mandates, $policy),
    $ids,
    $clock,
    $unitOfWork,
    $policy,
);
```

## Transactions

Several guarantees of the use cases are rollbacks, and a rollback needs a transactional `TransactionManager` (a Doctrine or
DBAL transaction around the operation): the whole transaction of `RequeueAfterInvalidCredentials` (nothing is requeued when
one record conflicts), the atomicity of `RegisterMandate` / `ImportMandate` / `CancelMandate` / `RecordMandateCancellation`
(mandate and account saved together), the retry of a conflicting mandate recording, and `ForgetEmployer`.

The default `ImmediateTransactionManager` (what the builder uses with the in-memory repositories) runs the operation with no
transaction: **nothing is rolled back**. A conflict found after the first save leaves the aggregates saved before it
(a mandate without its account stage, a partial requeue) and the events of that part are not published. In memory this
is acceptable (tests, demos: the next attempt of a mandate command completes the account, and `ImportMandate` /
`RegisterMandate` / `RecordMandateCancellation` complete an account left behind by a saved mandate, without any call); for real data, give your own
transaction manager and repositories (`withRepositories()`).

Whatever the transaction manager, once URSSAF accepted a call that could not be recorded the failure is a dedicated signal and
the call is never repeated: `MandateRecordingFailed` after 030 / 031 (recover with `ImportMandate` /
`RecordMandateCancellation`, no call; every other exception of the mandate commands means "not sent" or "unknown, check
pajemploi") and `DeclarationRecordingFailed` after 050 / 051 (carries the `referenceDocumentaire`; recover with
`ReconcileStaleDeclarations` then `ResolveDeclarationOutcome::asDeclared()`). See [workflow.md](workflow.md).
