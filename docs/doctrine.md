# Doctrine persistence

The Doctrine bridge (`TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine`) persists the three aggregates
(employer accounts, mandates, declaration records) with Doctrine ORM 3 / DBAL 4 (`composer.json` declares a `conflict`
with `doctrine/orm < 3.5.3`, `doctrine/dbal < 4.0` and `doctrine/doctrine-bundle < 2.19`; Doctrine itself is a `suggest`
of the package, never a hard requirement). The domain classes carry no Doctrine metadata: the mapping is a set of XML
files in `src/Bridge/Doctrine/Resources/mapping`. The decisions are in [ADR 0014](adr/0014-doctrine-bridge.md).

Classes: `DoctrineEmployerAccountRepository`, `DoctrineMandateRepository`, `DoctrineDeclarationRecordRepository`
(`Bridge\Doctrine\Repository`, implementing the domain repository interfaces), `DoctrineWorkflowStatistics`,
`DoctrineOutbox`, `DoctrineTransactionManager` (implements `TransactionManager`), `DoctrineIntegration` (types, mapping
driver, bundle configuration), plus the DBAL types (`Bridge\Doctrine\Type`) and the JSON serializers.

## Tables

All tables are prefixed with `urssaf_paje_` (`DoctrineIntegration::TABLE_PREFIX`), and the DBAL types, indexes and
constraints carry the same prefix: no name collides with the other URSSAF packages, so they can share a database or a
connection. Keep a dedicated entity manager per package all the same (see below): a save flushes the whole unit of work,
so a shared entity manager would write another package's pending changes.

| Table | Entity | Content |
|---|---|---|
| `urssaf_paje_employer_accounts` | `Domain\Employer\EmployerAccount` | Tracked employer: id, tenant, external reference, origin, creation instant, employer number and birth date, tracking (stage, last verification as JSON, last check and update instants). |
| `urssaf_paje_mandates` | `Domain\Mandate\Mandate` | Mandate of an employer: id, tenant, external reference, employer account, origin, state, employer number and birth date, registration and cancellation instants, update instant. |
| `urssaf_paje_declaration_records` | `Domain\Declaration\DeclarationRecord` | Declaration record: id, tenant, external reference, employer account, creation instant, snapshot of the declaration (employer number, activity, employee identification kind and number, fingerprint, period, payment date) and status (state, attempts, last attempt, URSSAF errors as JSON, result as JSON, outcome-unknown reason, update instant). |
| `urssaf_paje_active_mandates` | `Bridge\Doctrine\Entity\ActiveMandate` | Key table, see below. |
| `urssaf_paje_outbox` | `Bridge\Doctrine\Entity\OutboxMessage` | Integration events waiting to be relayed (`DoctrineOutbox`, written with DBAL in the transaction of the state change). See [integration-events.md](integration-events.md). |

There is no progress table and no `declaration_id` column: PAJE has no declaration identifier, the document reference of
an accepted declaration lives in the result document.

Unique constraints:

- `urssaf_paje_employer_accounts`: `(tenant_id, employer_number)` and `(tenant_id, external_reference)`.
- `urssaf_paje_declaration_records`: `(tenant_id, external_reference)`.
- `urssaf_paje_mandates`: none (an employer can have several mandates over time, the external reference is the
  employer's one).

Every column that a query filters or sorts on is a real column of the aggregate's own table; the last verification of an
employer, the URSSAF errors and the declaration result are JSON columns (no query filters on them, and Doctrine cannot
hydrate a nullable embeddable). Indexes serve the port queries (employers by last check and stage, mandates of an
employer by registration, declarations by state and update, stale submissions) and the erasure of
[ADR 0015](adr/0015-employer-erasure.md): `(tenant_id, employer_number, state)` on the declaration records serves
`countByEmployer()` and `deleteByEmployer()`.

### One active mandate per employer

"At most one non-cancelled mandate per (tenant, employer number)" ([ADR 0006](adr/0006-local-lifecycles.md)) is enforced
by the primary key `(tenant_id, employer_number)` of `urssaf_paje_active_mandates`: `DoctrineMandateRepository::save()`
rewrites the key row of the mandate with DBAL, in the transaction of the flush (the row is deleted when the mandate is
cancelled). Two concurrent registrations therefore cannot both succeed, on any platform (a partial unique index would not
exist on MySQL). The table is read-only for the ORM: never write it yourself.

## Registering types and mapping without Symfony

```php
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Doctrine\DoctrineIntegration;

DoctrineIntegration::registerTypes();                     // idempotent, global DBAL type registry
$configuration->setMetadataDriverImpl(DoctrineIntegration::mappingDriver());   // SimplifiedXmlDriver
```

`DoctrineIntegration::types()` returns the custom types by name (`urssaf_paje_*`), `mappingDirectory()` the XML
directory, `MAPPING_NAMESPACE` the namespace prefix handled by the simplified driver, and
`doctrineBundleConfiguration()` the array that the Symfony bundle prepends to the `doctrine` extension.

Then build the persistence from your `EntityManagerInterface` (see [standalone.md](standalone.md)):

```php
$package = UrssafTierceDeclarationPajeBuilder::create($transport, $credentials, $configuration)
    ->withDoctrine($entityManager)
    ->withOutbox(new DoctrineOutbox($entityManager), $sink)   // optional
    ->build();
```

### Lazy loading and PHP 8.4

Doctrine ORM 3 loads lazy references either through generated proxies built on the lazy ghosts of `symfony/var-exporter`
6.4 / 7 (the only option before PHP 8.4, and the default), or through the native lazy objects of PHP 8.4 (ORM 3.4+). The
lazy ghosts are gone from `symfony/var-exporter` 8: with that version, and without native lazy objects, the ORM throws
"Symfony LazyGhost is not available" as soon as it builds its proxy factory.

Without the Symfony bundle, enable the native objects yourself on PHP 8.4+:

```php
if (DoctrineIntegration::nativeLazyObjectsAvailable()) {   // PHP >= 8.4 and an ORM that implements them
    $configuration->enableNativeLazyObjects(true);
}
```

`doctrineBundleConfiguration()` adds `orm.enable_native_lazy_objects: true` under the same condition
(`doctrineBundleConfigurationWith([...])` takes the ORM options explicitly); with the Symfony bundle this is done for
you, and your own configuration still wins.

The mapped entities (`EmployerAccount`, `Mandate`, `DeclarationRecord` and the two bridge entities) are not declared
`final` so that Doctrine can generate their lazy-loading proxies with any `auto_generate_proxy_classes` mode (including
pre-generation at cache warm-up); they are final by convention (`@final`) and must not be extended. Value objects are
embeddables and custom DBAL types (`DoctrineIntegration::types()`); enums are mapped as backed enums.

## Dedicated entity manager (strongly recommended)

The repositories `persist()` and then `flush()` the entity manager they are built with on **every** `save()`. A Doctrine
flush has no per-entity variant (`flush($entity)` was removed in ORM 3): it writes **all** the pending changes of the
unit of work, host entities included. If the package shares the entity manager of your application, a `save()` made by
the package would therefore also flush half-finished changes of your own entities (and a rollback of the package
transaction would roll them back too).

Use a **dedicated entity manager** (and connection) for the package tables, and point the bundle at it:

```yaml
doctrine:
    dbal:
        connections:
            default: { url: '%env(resolve:DATABASE_URL)%' }
            urssaf_paje: { url: '%env(resolve:URSSAF_PAJE_DATABASE_URL)%' }   # same database is fine, different connection
    orm:
        entity_managers:
            default: { connection: default, mappings: { App: { type: attribute, dir: '%kernel.project_dir%/src/Entity', prefix: App\Entity } } }
            urssaf_paje: { connection: urssaf_paje }                          # the bundle adds the package mapping to this one

urssaf_tierce_declaration_paje:
    persistence: { driver: doctrine, entity_manager: urssaf_paje }
```

With a non-default `persistence.entity_manager` the bundle attaches the package mapping to that entity manager only, so
the host entities and the package entities never share a unit of work. When you must share the entity manager, flush
your own changes before calling a package handler, and accept that the handler flushes whatever is pending. The
`database schema` check of `urssaf:paje:check` warns when the `default` entity manager is used.

The `doctrine` driver needs DoctrineBundle: without it the container build fails with a `LogicException` (register the
bundle, or choose `in_memory` or `custom`). The driver is the default when `doctrine/orm` is installed.

## Identity and concurrency

**Managed instances.** Unlike the in-memory repositories, which hand out copies, a read returns the instance managed by
the entity manager: two reads of the same record give the same object, and a change made to it is visible to every
holder before it is saved. Call `EntityManager::clear()` to forget the managed instances (the Symfony Messenger worker
does it between two messages for every entity manager of DoctrineBundle). After `clear()`, read the aggregate again
before saving it: an instance read before the `clear()` is detached and its save is refused with
`<aggregate>.concurrent_modification` when its identifier is stored.

**Optimistic locking.** `EmployerAccount`, `Mandate` and `DeclarationRecord` carry a persistence concurrency token
(`version`, a native Doctrine version field: not a domain concept, no accessor) incremented by every update. When a
record was changed by another process (or another entity manager) since it was read, the save is refused before anything
is flushed (the entity manager stays usable) and the repository throws an `InvariantViolation` with the code `employer_account.concurrent_modification`,
`mandate.concurrent_modification` or `declaration_record.concurrent_modification` (translated, see
[symfony.md](symfony.md)). Nothing was written: read the record again and redo the operation. The claim of a declaration
(see below) and any bulk update of these tables also increment the version. The ports define this contract for every
repository, in-memory ones included ([ADR 0006](adr/0006-local-lifecycles.md)); the bridge is tested against the same
shared scenarios with a second entity manager over the same database (a stale save after another writer, a delete then a
stale save, a claim then a stale save).

The race it closes: a `DeclareSalary` call slower than `workflow.stale_submission_after` while the stale reconciliation
moves the record to `outcome_unknown`. The slow call's answer is settled against the record as re-read when it arrives
([ADR 0006](adr/0006-local-lifecycles.md), settlement table): an accepted answer is never discarded, a concurrent
modification during that save is retried a bounded number of times, then reported as `DeclarationRecordingFailed`
carrying the document reference ([workflow.md](workflow.md)).

**A failed flush closes the entity manager.** When a flush fails (a concurrent insert violated a unique constraint, a
database error, or a concurrent write between the version check and the flush), the ORM closes the entity manager: it cannot be used again. The repositories
still report the unique-constraint case as the domain code (`employer_account.duplicate_employer_number`...), but the
caller of a long-running process must reset the entity manager (`ManagerRegistry::resetManager()`, or let the Messenger
worker do it). The package never resets it for you.

**A save flushes every pending package change.** The version check before the flush covers the aggregate being saved;
another package aggregate that is dirty in the same unit of work and was changed by another writer since it was read is
caught by Doctrine's native version check instead: the flush fails with an `OptimisticLockException`, the entity manager
is closed, and the refusal carries the code of the aggregate being saved. Nothing is lost; keep one aggregate change per
unit of work (the package use cases do) and go through `DoctrineTransactionManager`, which detaches the package
entities after a rollback. A save made outside it that fails on the active-mandate key leaves the other entities of the
rolled-back flush managed with a version the database never stored: their next save is refused as
`concurrent_modification` (spurious, safe).

## Personal data (GDPR)

The tables hold the personal data that the workflow needs ([ADR 0007](adr/0007-personal-data.md)):

- the **employer birth date** (`urssaf_paje_employer_accounts.birth_date` and `urssaf_paje_mandates.birth_date`, required
  by the employer verification and the mandate methods), under its own DBAL type `urssaf_paje_birth_date` shared by both
  columns, so that it can be encrypted alone;
- the **URSSAF error descriptions** of a rejected declaration (`urssaf_paje_declaration_records.errors`), which may echo
  values that the host sent;
- the **amounts of the declaration result** (`urssaf_paje_declaration_records.result`: salary, contributions, amounts of
  the document), needed to answer `GetDeclaration`;
- the **last employer verification** (`urssaf_paje_employer_accounts.last_verification`): the online account state URSSAF
  sent.

Never stored: the NIR, the employee and child names, the address, the IBAN and BIC, the e-mail and phone number (a test
asserts that no column of the package tables looks like one). The check results of the children (names and birth dates)
are not written either: a result is read back without them. The employer number and the employee number are stored. A
conversion error on a number, a birth date or a JSON document never contains the stored value (DBAL's message receives
`***`). The package gives you the tools, the policy is yours:

1. **Retention.** Define how long an account, a mandate and a declaration record are kept after they are no longer
   needed, and run a scheduled job in your application that selects the expired records.
2. **Erasure by deletion.** The `ForgetEmployer` use case (and its console command `urssaf:paje:employer:forget`)
   erases a tracked employer, its mandates (and their `urssaf_paje_active_mandates` key row, so that the employer can
   hold a new active mandate afterwards) and its declaration records in one transaction, then records the
   `urssaf.paje.employer.forgotten` integration event ([ADR 0015](adr/0015-employer-erasure.md), the rules are in
   [workflow.md](workflow.md)). Nothing is erased at URSSAF: the data they hold is subject to their own retention rules.
   For your own retention jobs, the repositories expose the building blocks: `delete()` on `EmployerAccountRepository`,
   `MandateRepository` and `DeclarationRecordRepository` (one row, no event, nothing happens when it does not exist), and
   `DeclarationRecordRepository::deleteByEmployer()` (the records of an employer `(tenant, employer number)` in the given
   states, returning their ids) with `countByEmployer()`. The Doctrine bridge deletes with an immediate DQL `DELETE` after
   detaching the managed copies, and the index `(tenant_id, employer_number, state)` serves both. The outbox entries are
   not purged by an erasure (they hold identifiers only); `urssaf:paje:outbox:purge` removes the dispatched ones after
   `outbox.retention`.
3. **Encryption at rest.** The types are `final`: to encrypt a column, write a type of your own that wraps the package
   one and register it under the same name, after `DoctrineIntegration::registerTypes()`. Example for the birth date:

   ```php
   final class EncryptedBirthDateType extends Type
   {
       public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
       {
           // The mapping declares a date column: widen it to a string for the ciphertext.
           return $platform->getStringTypeDeclarationSQL(['length' => 255] + $column);
       }

       public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
       {
           $plain = (new BirthDateType())->convertToDatabaseValue($value, $platform);

           return $plain === null ? null : $this->cipher()->encrypt($plain);
       }

       public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?CalendarDate
       {
           return (new BirthDateType())->convertToPHPValue($value === null ? null : $this->cipher()->decrypt((string) $value), $platform);
       }
   }

   DoctrineIntegration::registerTypes();
   Type::overrideType(BirthDateType::NAME, EncryptedBirthDateType::class);
   ```

   With the Symfony bundle, register it in the configuration of DoctrineBundle (your configuration wins over the one
   the bundle prepends):

   ```yaml
   doctrine:
       dbal:
           types:
               urssaf_paje_birth_date: App\Doctrine\EncryptedBirthDateType
   ```

   One override covers the employer **and** the mandate birth-date columns. The same pattern covers the JSON types
   (`EmployerVerificationType`, `SubmissionErrorsType`, `DeclarationResultType`: the JSON column becomes text). No query
   filters on these columns and no constraint or index uses them, so encryption breaks nothing. Use authenticated
   encryption with a key kept outside the database (KMS, Symfony secrets), and re-run `doctrine:migrations:diff` so that
   the column is wide enough for the ciphertext. Do not encrypt `employer_number`, `employee_number` or the identifiers:
   they are part of unique constraints and indexes.
4. **Backups and logs.** Backups keep the data that was erased afterwards: bound their retention and replay the erasures
   after a restore. The package never logs personal data, and the value objects mask it in `var_dump()`.

## Migrations

The package ships **no migration**: the schema depends on your platform and on the rest of your schema. Generate it in
your application:

```bash
php bin/console doctrine:migrations:diff --em=urssaf_paje
php bin/console doctrine:migrations:migrate
```

(or `doctrine:schema:update --dump-sql` for a quick look). Re-run `diff` after each upgrade of the package: the mapping
may evolve (see the changelog). `urssaf:paje:check` reports the pending changes of the package tables (`database schema`
check, failure when a change is pending).

## Transactions

`DoctrineTransactionManager::transactional()` runs the operation in a DBAL transaction. The repositories flush on every
save, so those flushes join the surrounding transaction and become durable together (this is what makes the mandate and
its account saved together, a supersede, `delete()` then `save()` of the declaration record, and the outbox write
atomic). On failure the transaction is rolled back, the package entities still managed by the entity manager
(`EmployerAccount`, `Mandate`, `DeclarationRecord`) are detached so that none of them keeps a state that was rolled
back, and the throwable is rethrown unchanged. The entity manager is neither cleared nor closed: the entities of your
application stay managed (domain exceptions are ordinary control flow). Domain events are published by `UnitOfWork` only
after the commit.

Because every repository `save()` flushes (see [Dedicated entity manager](#dedicated-entity-manager-strongly-recommended)),
changes to host entities pending in the same entity manager are written, and rolled back, with the package
transaction: use a dedicated entity manager.

If a handler runs inside a transaction opened by your application, the package transaction becomes a nested one
(a savepoint when the platform supports it): its changes become durable only when your outer transaction commits, but
the domain events are published when the nested transaction returns, i.e. before that commit. See the outbox pattern in
[workflow.md](workflow.md) and [integration-events.md](integration-events.md).

## Claim semantics

`claimForSubmission(id, now): bool` is the concurrency guard of `DeclareSalary`. It is one conditional
`UPDATE ... SET state = 'submitting', submission_attempts = submission_attempts + 1, last_attempt_at = :now ... WHERE id =
:id AND state = 'pending'`: exactly one worker wins (returns `true`); the others get `false` and the handler reports
`not_claimed`. It records no domain event, never loads the aggregate (the managed copy is detached, so call `get()` again
to read the claimed row) and increments the version.

A record left in `submitting` (worker crash, lost answer) is never resubmitted blindly: see the stale reconciliation in
[workflow.md](workflow.md).

## Ordering and portability

The queries use DQL and the DBAL query builder only, no driver-specific SQL, and never rely on "affected rows" except
for the claim (whose `UPDATE` always changes a value) and for Doctrine's versioned `UPDATE` (safe because the version
always changes). `NULL` never decides an order: accounts never checked come first through an explicit rank, then ties are
broken by the last check, the creation instant and the id (mandates and records likewise end on the id) (the in-memory "insertion order" has no SQL equivalent).
`tenants()` lists the tenants in the order of the database collation, which is the byte order for the ASCII tenant ids
the package expects. The test suite runs on SQLite.

## Uniqueness error codes

`save()` translates a duplicate into an `InvariantViolation` (a `UrssafException`) with the same stable code as the
in-memory repositories; a concurrent insert that hits the unique constraint first is reported with the same code:

| Code | Constraint |
|---|---|
| `employer_account.duplicate_employer_number` | `(tenant_id, employer_number)` of the accounts |
| `employer_account.duplicate_external_reference` | `(tenant_id, external_reference)` of the accounts |
| `mandate.duplicate_active` | one non-cancelled mandate per `(tenant_id, employer_number)` (key table) |
| `declaration_record.duplicate_external_reference` | `(tenant_id, external_reference)` of the records |
| `employer_account.concurrent_modification`, `mandate.concurrent_modification`, `declaration_record.concurrent_modification` | optimistic lock lost (see above) |

The use cases check these conditions first and raise their own exceptions; the constraints are the safety net against
concurrent inserts.
