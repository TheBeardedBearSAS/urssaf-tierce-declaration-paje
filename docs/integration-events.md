# Integration events (outbox)

The host application usually needs to react to the workflow: tell an operator that a declaration needs a manual check,
mark a payslip as declared, know that the mandate is registered. The package publishes these facts as **integration
events**: public, scalar classes (`TheBeardedBear\UrssafTierceDeclarationPaje\Contract\Event\*`,
[ADR 0012](adr/0012-integration-events.md)) versioned independently of the domain model, written to an **outbox** in
the same transaction as the state change, then delivered by a **relay**.

Guarantees:

- **Durable**: an event exists if and only if its state change was committed (a rolled back use case writes none).
- **At least once**: an event is marked as dispatched only once the sink accepted it; a crash between the delivery and
  the mark delivers it again. Deduplicate on `eventId`: the same fact always has the same id.
- **Ordered per record**: the events of one employer account, mandate or declaration record are delivered in recording
  order; when one fails, the next events of the same record wait for the next run (the other records go on).

The domain events (`Domain\...\Event\*`, dispatched after the commit) are an internal mechanism and may change with the
domain model. Integrate with the integration events.

Payloads carry identifiers, activity type, period, states and codes. They never carry the Pajemploi numbers (employer
or employee), birth dates, NIR, names, amounts nor a URSSAF description ([ADR 0007](adr/0007-personal-data.md)).

## Catalogue

Common properties: `eventId`, `occurredAt` (ISO 8601, UTC), `tenantId`, `aggregateId` (employer account id, mandate id
or declaration record id), `externalReference` (your reference of the employer or the declaration); `type()` and
`version()` (1 for every type today; a change of payload bumps it).

Declaration events share `employerAccountId` (`null` when the employer is not tracked), `activityType`
(`childminder`, `home_care`), `periodStart` and `periodEnd` (`yyyy-MM-dd`).

| Class | `type()` | When | Specific properties |
|---|---|---|---|
| `EmployerStatusChanged` | `urssaf.paje.employer.status_changed` | a 020 refresh, or a stage change after 030 / 031 | `previousStage`, `stage` (`tracked`, `mandate_registered`, `mandate_cancelled`), `authorised`, `onlineAccountState` |
| `EmployerForgotten` | `urssaf.paje.employer.forgotten` | an employer was erased from the package store (`ForgetEmployer`, [ADR 0015](adr/0015-employer-erasure.md)) | `mandateIds`, `declarationRecordIds` |
| `MandateRegistered` | `urssaf.paje.mandate.registered` | a mandate is registered (030) or imported | `employerAccountId`, `origin` (`registered`, `imported`) |
| `MandateCancelled` | `urssaf.paje.mandate.cancelled` | the mandate was cancelled (031) | `employerAccountId` |
| `DeclarationDeclared` | `urssaf.paje.declaration.declared` | URSSAF recorded the declaration (050 / 051), or an operator resolved an unknown outcome | declaration properties, `referenceDocumentaire` (nullable), `nonBlockingCodes` |
| `DeclarationRejected` | `urssaf.paje.declaration.rejected` | URSSAF refused the declaration (final) | declaration properties, `errorCodes` |
| `DeclarationOutcomeUnknown` | `urssaf.paje.declaration.outcome_unknown` | the outcome is unknown, manual review needed ([ADR 0003](adr/0003-retry-and-unknown-outcome.md)) | declaration properties, `reason` (`may_have_reached_urssaf`, `stale_submission`) |
| `DeclarationAlreadyDeclared` | `urssaf.paje.declaration.already_declared` | URSSAF answered that it already holds the declaration (final) | declaration properties, `errorCodes` |

Notes:

- `EmployerStatusChanged` raised by a local change (030 / 031) has `authorised` and `onlineAccountState` set to `null`:
  there is no 020 answer. `onlineAccountState` is the raw wire value (unknown ones are kept).
- `DeclarationDeclared` carries the `referenceDocumentaire` (`null` when URSSAF answered without one) and the codes of the non-blocking messages only; read the
  amounts and the rate with `GetDeclaration`.
- `DeclarationAlreadyDeclared` means URSSAF already holds a declaration for the key (employee, period and child, or
  employee and month), possibly from another channel: it is counted under `needingAttention()`, check it.
- No event exists for requeued declarations, failed submissions and rejected credentials: monitor them with
  `GetWorkflowOverview` (`needingAttention()`) and list them with `FindDeclarations::inState()`.

### Erasure

`EmployerForgotten` tells you to erase your own copies of the employer, its mandates and its declarations: `aggregateId`
is the erased employer account id, `mandateIds` and `declarationRecordIds` are the `aggregateId` of the erased mandate
and declaration events (oldest first). It carries identifiers only. Nothing was erased at URSSAF.

The outbox is **not** purged by the erasure: the entries already dispatched are deleted by the purge after
`outbox.retention` (default `P30D`), the pending ones stay until delivered. An event of a listed mandate or declaration
delivered **after** `employer.forgotten` must be ignored, never used to recreate a copy.

## Versioning

Each class has `TYPE` and `VERSION` constants. Adding, removing, renaming or changing the meaning of a property bumps
`VERSION` of that type; the type name never changes. `IntegrationEventCodec::CLASSES` is the allow-list used to decode
the outbox payloads (PHP serialization restricted to the eight event classes); a new type is added there.

## Handling

```php
final readonly class MarkDeclarationDeclared
{
    public function __construct(private PayslipRepository $payslips) {}

    public function __invoke(DeclarationDeclared $event): void
    {
        // The same event may be delivered twice: deduplicate on $event->eventId.
        $this->payslips->byReference($event->externalReference)->markDeclared($event->referenceDocumentaire, $event->eventId);
    }
}
```
