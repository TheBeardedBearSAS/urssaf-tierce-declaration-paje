<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Testing;

use Closure;
use LogicException;
use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\OperationRetryClass;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\ApiError;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\OutcomeUnknown;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\PajeGateway;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Child\Child;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Child\ChildCheck;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Child\ChildEntitlement;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Child\ChildEntitlementStatus;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\ChildminderDeclaration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\Declaration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationActivity;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationAmounts;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationResult;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\HomeCareDeclaration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\PreDeclarationResult;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\WithholdingTax;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\ActivityType;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeAccount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeAssociation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeCreation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeIdentification;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\PajeEmployeeNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerVerification;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\OnlineAccountState;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\PajeEmployerNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Estimate\ChildminderEstimateRequest;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Estimate\ContributionEstimate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Estimate\HomeCareEstimateRequest;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Message\NonBlockingMessage;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Amount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Exception\ApiErrorCode;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\ErrorClassifier;
use Throwable;

/**
 * An in-memory URSSAF PAJE platform for the tests of a host application: it keeps the employers, the mandates, the
 * employees, the associations and the declarations, and answers the twelve operations like the real API would, without
 * any network. It never contacts URSSAF.
 *
 * Scoping: employers and employees are global (one Pajemploi registry); a mandate is exclusive (one holder tenant per
 * employer); declarations and the duplicate rule are employer-level state.
 *
 * Default behaviour (every step can be scripted, see failNext(), refuseNext(), loseNextAnswer()):
 * - 020: unknown number: ER_API_020_0010; birth date that differs: ER_API_020_0000; otherwise the account is "Actif"
 *   and `authorised` tells whether the calling tenant holds the mandate;
 * - 021: a known employee (see knowEmployee()) is found with its account, any other answers the state "Absent";
 * - 022: a child known as entitled (see entitleChild()) is `Entitled`, any other `NotEntitled`;
 * - 023: unknown employer: PE_INEXISTANT; a known employee is associated (already associated: the non-blocking
 *   ER_API_023_NB_0000); an unknown employee is created when creation data is given, otherwise PARAM_INVALIDE;
 * - 030 / 031: unknown number or birth date that differs: ER_API_020_0000; mandate held by another tenant:
 *   COMPTE_DEJA_MANDATE;
 * - 040 / 041: the current estimate (default 63.10 EUR employer share), no employer context;
 * - 045 / 046 / 050 / 051: unknown number: ER_API_020_0010; birth date that differs: ER_API_020_0000; a duplicate
 *   (ER_API_050_0070 for a childminder: same employee, period and child; ER_API_DECLA_00020 for home care: same
 *   employee and month) is refused; otherwise the amounts (see setAmounts()). 050 and 051 record the declaration and
 *   give it a new document reference (SIM-DECL-000001...), 045 and 046 record nothing.
 *
 * Mandate: the specification does not say which operations need one (docs/api-reference.md §5), so by default none is
 * required. requireMandate() makes the four declaration operations answer MANDAT_INEXISTANT for a tenant that does not
 * hold the mandate.
 *
 * Rejections are classified by the real ErrorClassifier (ADR 0005). Never use it outside tests: nothing is sent to
 * URSSAF, and the HTTP gateway is the only one the standalone builder accepts in production.
 *
 * Implements the twelve operations plus the scripting and inspection API, hence the method count and complexity.
 *
 * @mago-expect lint:too-many-methods
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class SimulatedPajeGateway implements PajeGateway
{
    public const DEFAULT_EMPLOYER_SHARE_CENTS = 6310;

    private const MESSAGE_ALREADY_ASSOCIATED = 'Salarié / Employeur déjà associé et actif';

    private const DOCUMENT_REFERENCE_FORMAT = 'SIM-DECL-%06d';

    private const EMPLOYEE_NUMBER_FORMAT = 'SIMSAL%08d';

    private readonly SimulatedScript $script;

    private readonly SimulatedSequence $documentReferences;

    private readonly SimulatedSequence $employeeNumbers;

    private ContributionEstimate $estimate;

    private DeclarationAmounts $amounts;

    private bool $mandateRequired = false;

    /** @var array<string, SimulatedEmployer> by Pajemploi number */
    private array $employers = [];

    /** @var list<SimulatedEmployee> */
    private array $employees = [];

    /** @var list<SimulatedDeclaration> */
    private array $declarations = [];

    /** @var list<Operation> */
    private array $calls = [];

    public function __construct()
    {
        $this->script = new SimulatedScript();
        $this->documentReferences = new SimulatedSequence(self::DOCUMENT_REFERENCE_FORMAT);
        $this->employeeNumbers = new SimulatedSequence(self::EMPLOYEE_NUMBER_FORMAT);
        $this->estimate = ContributionEstimate::of(null, Amount::fromCents(self::DEFAULT_EMPLOYER_SHARE_CENTS), null, []);
        $this->amounts = DeclarationAmounts::of(
            null,
            null,
            Amount::fromCents(self::DEFAULT_EMPLOYER_SHARE_CENTS),
            WithholdingTax::of(null, null, null, null),
            null,
            null,
            null,
            null,
            null,
            null,
        );
    }

    /**
     * The next call of the operation throws the failure before anything is processed (for example
     * `UrssafUnavailable::withStatus(...)`, `UrssafAuthenticationFailed::withStatus(401)`, or an `OutcomeUnknown` for
     * "sent but not processed"); failures queued for the same operation are thrown in order.
     */
    public function failNext(Operation $operation, Throwable $failure): void
    {
        $this->script->fail($operation, $failure);
    }

    /**
     * The next call of the operation is refused (HTTP 400) with this error, classified like the real API (ADR 0005);
     * refusals queued for the same operation are answered in order. Use the codes of {@see ApiErrorCode}, for instance
     * `ER_API_DECLA_00020` to make a declaration look like a duplicate.
     */
    public function refuseNext(Operation $operation, string $code, string $message, ?string $description): void
    {
        $this->script->refuse($operation, ApiErrors::of(ApiError::of($code, $message, $description)));
    }

    /**
     * The next call of the write operation is processed (state changed, document reference consumed) but its answer is
     * lost: an OutcomeUnknown (HTTP 504) is thrown, the case operators must reconcile (ADR 0003). "Sent but not
     * processed" is scripted with failNext().
     *
     * @throws LogicException when the operation is safe to retry (it has no unknown outcome)
     */
    public function loseNextAnswer(Operation $operation): void
    {
        if ($operation->retryClass() === OperationRetryClass::Safe) {
            throw new LogicException(\sprintf(
                'Only the writes 023, 030, 031, 050 and 051 have an unknown outcome; method %s does not.',
                $operation->value,
            ));
        }

        $this->script->loseAnswer($operation);
    }

    /**
     * An existing employer account (online account "Actif", no mandate, no entitled child, no associated employee). No
     * call is recorded.
     *
     * @throws LogicException when the employer is already known
     */
    public function knowEmployer(#[SensitiveParameter] EmployerReference $employer): void
    {
        $key = $employer->number()->value();
        if (\array_key_exists($key, $this->employers)) {
            throw new LogicException('The employer is already known to the simulator.');
        }

        $this->employers[$key] = new SimulatedEmployer($employer);
    }

    /**
     * The tenant holds the mandate of the employer, obtained outside the package (no call recorded); it replaces any
     * holder.
     */
    public function mandate(#[SensitiveParameter] PajeEmployerNumber $employer, TenantId $tenantId): void
    {
        $this->employer($employer)->holdBy($tenantId);
    }

    /**
     * The mandate disappears at URSSAF (for example cancelled by the employer): method 020 reports the employer as not
     * authorised.
     */
    public function withdrawMandate(#[SensitiveParameter] PajeEmployerNumber $employer): void
    {
        $this->employer($employer)->holdBy(null);
    }

    /**
     * Raw online account state returned by method 020 (unknown values allowed, for example "Inactif").
     */
    public function setOnlineAccountState(#[SensitiveParameter] PajeEmployerNumber $employer, string $state): void
    {
        $this->employer($employer)->setOnlineAccountState($state);
    }

    /**
     * An employee known to the Pajemploi repository (methods 021 and 023): found by its number or by any of the given
     * identifications.
     */
    public function knowEmployee(
        #[SensitiveParameter]
        PajeEmployeeNumber $number,
        bool $childminder,
        bool $homeCare,
        #[SensitiveParameter]
        EmployeeIdentification ...$aliases,
    ): void {
        $keys = [SimulatedKey::employeeNumber($number->value())];
        foreach ($aliases as $alias) {
            $keys[] = SimulatedKey::employee($alias);
        }
        $this->employees[] = new SimulatedEmployee($number->value(), $childminder, $homeCare, $keys);
    }

    /**
     * A child that method 022 reports as entitled for the employer.
     */
    public function entitleChild(#[SensitiveParameter] PajeEmployerNumber $employer, #[SensitiveParameter] Child $child): void
    {
        $this->employer($employer)->entitle($child);
    }

    /**
     * Result of the next estimates (methods 040 and 041), until changed.
     */
    public function setEstimate(ContributionEstimate $estimate): void
    {
        $this->estimate = $estimate;
    }

    /**
     * Amounts of the next pre-declarations and declarations (methods 045, 046, 050 and 051), until changed.
     */
    public function setAmounts(DeclarationAmounts $amounts): void
    {
        $this->amounts = $amounts;
    }

    /**
     * From now on, methods 045, 046, 050 and 051 answer MANDAT_INEXISTANT for a tenant that does not hold the mandate
     * of the employer.
     */
    public function requireMandate(): void
    {
        $this->mandateRequired = true;
    }

    /**
     * @return list<Operation> every call received, in order (failed, refused and lost ones included)
     */
    public function calls(): array
    {
        return $this->calls;
    }

    /**
     * @return list<string> the document references of the declarations recorded (accepted or answer lost), in order
     */
    public function documentReferences(): array
    {
        return array_map(static fn(SimulatedDeclaration $declaration): string => $declaration->documentReference, $this->declarations);
    }

    public function isMandated(#[SensitiveParameter] PajeEmployerNumber $employer, TenantId $tenantId): bool
    {
        return ($this->employers[$employer->value()] ?? null)?->isMandatedBy($tenantId) ?? false;
    }

    public function verifyEmployer(TenantId $tenantId, #[SensitiveParameter] EmployerReference $employer): EmployerVerification
    {
        return $this->process(Operation::VerifyEmployer, function () use ($tenantId, $employer): EmployerVerification {
            $known = $this->employerMatching(Operation::VerifyEmployer, $employer, ApiErrorCode::EmployerNotFound);

            return EmployerVerification::of($known->isMandatedBy($tenantId), OnlineAccountState::of($known->onlineAccountState()));
        });
    }

    public function verifyEmployee(TenantId $tenantId, #[SensitiveParameter] EmployeeIdentification $employee): EmployeeAccount
    {
        return $this->process(Operation::VerifyEmployee, function () use ($employee): EmployeeAccount {
            $known = $this->employeeFound($employee);

            return $known instanceof SimulatedEmployee
                ? $known->account()
                : EmployeeAccount::of(null, false, false, OnlineAccountState::of('Absent'));
        });
    }

    public function verifyChildren(TenantId $tenantId, #[SensitiveParameter] EmployerReference $employer, #[SensitiveParameter] array $children): array
    {
        return $this->process(Operation::VerifyChildren, function () use ($employer, $children): array {
            $known = $this->employerMatching(Operation::VerifyChildren, $employer, ApiErrorCode::EmployerNotFound);

            return array_map(
                static fn(Child $child): ChildCheck => ChildCheck::of(
                    $child,
                    ChildEntitlementStatus::of($known->isEntitled($child) ? ChildEntitlement::Entitled->value : ChildEntitlement::NotEntitled->value),
                ),
                $children,
            );
        });
    }

    public function associateEmployee(
        TenantId $tenantId,
        #[SensitiveParameter]
        EmployerReference $employer,
        #[SensitiveParameter]
        EmployeeIdentification $employee,
        #[SensitiveParameter]
        EmployeeCreation $creation,
    ): EmployeeAssociation {
        return $this->process(Operation::AssociateEmployee, function () use ($employer, $employee, $creation): EmployeeAssociation {
            $known = $this->employerMatching(Operation::AssociateEmployee, $employer, ApiErrorCode::EmployerMissing);
            $found = $this->employeeFound($employee);

            if ($found instanceof SimulatedEmployee) {
                return $this->associate($known, $found);
            }
            if ($creation->isEmpty()) {
                throw self::refusal(Operation::AssociateEmployee, self::invalidParameter());
            }

            $homeCare = $creation->activityType()?->equals(ActivityType::homeCare()) === true;
            $created = new SimulatedEmployee(
                $this->employeeNumbers->next(),
                !$homeCare,
                $homeCare,
                [SimulatedKey::employee($employee)],
            );
            $this->employees[] = $created;
            $known->associate($created);

            return EmployeeAssociation::of(true, true, false, $created->account(), []);
        });
    }

    public function registerMandate(TenantId $tenantId, #[SensitiveParameter] EmployerReference $employer): void
    {
        $this->process(Operation::RegisterMandate, function () use ($tenantId, $employer): null {
            $this->mandateTarget(Operation::RegisterMandate, $tenantId, $employer)->holdBy($tenantId);

            return null;
        });
    }

    public function cancelMandate(TenantId $tenantId, #[SensitiveParameter] EmployerReference $employer): void
    {
        $this->process(Operation::CancelMandate, function () use ($tenantId, $employer): null {
            $this->mandateTarget(Operation::CancelMandate, $tenantId, $employer)->holdBy(null);

            return null;
        });
    }

    public function estimateChildminder(TenantId $tenantId, ChildminderEstimateRequest $request): ContributionEstimate
    {
        return $this->process(Operation::EstimateChildminder, fn(): ContributionEstimate => $this->estimate);
    }

    public function estimateHomeCare(TenantId $tenantId, HomeCareEstimateRequest $request): ContributionEstimate
    {
        return $this->process(Operation::EstimateHomeCare, fn(): ContributionEstimate => $this->estimate);
    }

    public function preDeclareChildminder(
        TenantId $tenantId,
        #[SensitiveParameter]
        EmployerReference $employer,
        #[SensitiveParameter]
        ChildminderDeclaration $declaration,
    ): PreDeclarationResult {
        return $this->preDeclare(Operation::PreDeclareChildminder, $tenantId, $employer, $declaration);
    }

    public function preDeclareHomeCare(
        TenantId $tenantId,
        #[SensitiveParameter]
        EmployerReference $employer,
        #[SensitiveParameter]
        HomeCareDeclaration $declaration,
    ): PreDeclarationResult {
        return $this->preDeclare(Operation::PreDeclareHomeCare, $tenantId, $employer, $declaration);
    }

    public function declareChildminder(
        TenantId $tenantId,
        #[SensitiveParameter]
        EmployerReference $employer,
        #[SensitiveParameter]
        ChildminderDeclaration $declaration,
    ): DeclarationResult {
        return $this->declare(Operation::DeclareChildminder, $tenantId, $employer, $declaration);
    }

    public function declareHomeCare(
        TenantId $tenantId,
        #[SensitiveParameter]
        EmployerReference $employer,
        #[SensitiveParameter]
        HomeCareDeclaration $declaration,
    ): DeclarationResult {
        return $this->declare(Operation::DeclareHomeCare, $tenantId, $employer, $declaration);
    }

    private function preDeclare(Operation $operation, TenantId $tenantId, EmployerReference $employer, Declaration $declaration): PreDeclarationResult
    {
        return $this->process($operation, function () use ($operation, $tenantId, $employer, $declaration): PreDeclarationResult {
            $this->checkDeclaration($operation, $tenantId, $employer, $declaration);

            return PreDeclarationResult::of($this->amounts, false, null, [], []);
        });
    }

    private function declare(Operation $operation, TenantId $tenantId, EmployerReference $employer, Declaration $declaration): DeclarationResult
    {
        return $this->process($operation, function () use ($operation, $tenantId, $employer, $declaration): DeclarationResult {
            $this->checkDeclaration($operation, $tenantId, $employer, $declaration);

            $reference = $this->documentReferences->next();
            $this->declarations[] = SimulatedDeclaration::of($employer, $declaration, $reference);

            return DeclarationResult::of($reference, $this->amounts, false, null, [], []);
        });
    }

    /**
     * The rules shared by the four declaration operations: the employer, the optional mandate, the duplicate rule.
     */
    private function checkDeclaration(Operation $operation, TenantId $tenantId, EmployerReference $employer, Declaration $declaration): void
    {
        $known = $this->employerMatching($operation, $employer, ApiErrorCode::EmployerNotFound);
        if ($this->mandateRequired && !$known->isMandatedBy($tenantId)) {
            throw self::refusal($operation, self::mandateMissing());
        }
        foreach ($this->declarations as $recorded) {
            if ($recorded->duplicates($employer, $declaration)) {
                throw self::refusal($operation, self::duplicateDeclaration($declaration));
            }
        }
    }

    private function associate(SimulatedEmployer $employer, SimulatedEmployee $employee): EmployeeAssociation
    {
        if ($employer->isAssociated($employee)) {
            return EmployeeAssociation::of(
                false,
                false,
                true,
                $employee->account(),
                [NonBlockingMessage::of(ApiErrorCode::EmployeeAlreadyAssociated->value, self::MESSAGE_ALREADY_ASSOCIATED, null)],
            );
        }

        $employer->associate($employee);

        return EmployeeAssociation::of(false, true, false, $employee->account(), []);
    }

    private function employeeFound(EmployeeIdentification $employee): ?SimulatedEmployee
    {
        $key = SimulatedKey::employee($employee);
        foreach ($this->employees as $known) {
            if ($known->isFoundBy($key)) {
                return $known;
            }
        }

        return null;
    }

    /**
     * The common path of the operations: record the call, throw a scripted failure, then apply a scripted refusal or the
     * built-in rules; a lost answer throws OutcomeUnknown whatever the outcome (the state change of an accepted call is
     * kept, a refusal is swallowed).
     *
     * @template T
     *
     * @param-immediately-invoked-callable $rules
     * @param Closure(): T $rules
     *
     * @throws Throwable
     *
     * @return T
     */
    private function process(Operation $operation, Closure $rules): mixed
    {
        $this->calls[] = $operation;

        $failure = $this->script->nextFailure($operation);
        if ($failure instanceof Throwable) {
            throw $failure;
        }

        $lost = $this->script->takeLostAnswer($operation);
        $refusal = $this->script->nextRefusal($operation);
        if (!$lost) {
            return $this->apply($operation, $refusal, $rules);
        }

        try {
            $this->apply($operation, $refusal, $rules);
            // @mago-expect lint:no-empty-catch-clause The answer is lost: a refusal never reaches the caller, only OutcomeUnknown does.
        } catch (UrssafRequestRejected) {
        }

        throw OutcomeUnknown::fromUnavailable(UrssafUnavailable::withStatus($operation, 504, null, ApiErrors::none()));
    }

    /**
     * A scripted refusal replaces the built-in rules.
     *
     * @template T
     *
     * @param-immediately-invoked-callable $rules
     * @param Closure(): T $rules
     *
     * @throws UrssafRequestRejected
     *
     * @return T
     */
    private function apply(Operation $operation, ?ApiErrors $refusal, Closure $rules): mixed
    {
        if ($refusal instanceof ApiErrors) {
            throw self::refusal($operation, $refusal);
        }

        return $rules();
    }

    /**
     * The employer of the registry with this number and birth date; the operation is refused with the "not found" code
     * when the number is unknown and with ER_API_020_0000 when the birth date differs.
     */
    private function employerMatching(Operation $operation, EmployerReference $employer, ApiErrorCode $unknown): SimulatedEmployer
    {
        $known = $this->employers[$employer->number()->value()] ?? null;
        if (!$known instanceof SimulatedEmployer) {
            throw self::refusal($operation, self::accountError($unknown));
        }
        if (!$known->matches($employer)) {
            throw self::refusal($operation, self::accountError(ApiErrorCode::BirthDateMismatch));
        }

        return $known;
    }

    /**
     * Methods 030 and 031: the employer of the registry with this birth date, not held by another tenant.
     */
    private function mandateTarget(Operation $operation, TenantId $tenantId, EmployerReference $employer): SimulatedEmployer
    {
        $known = $this->employers[$employer->number()->value()] ?? null;
        if (!$known instanceof SimulatedEmployer || !$known->matches($employer)) {
            throw self::refusal($operation, self::accountError(ApiErrorCode::BirthDateMismatch));
        }
        if ($known->isHeldByAnotherThan($tenantId)) {
            throw self::refusal($operation, self::mandateHeldElsewhere());
        }

        return $known;
    }

    private function employer(PajeEmployerNumber $number): SimulatedEmployer
    {
        return $this->employers[$number->value()] ?? throw new LogicException('The employer is not known to the simulator.');
    }

    private static function refusal(Operation $operation, ApiErrors $errors): UrssafRequestRejected
    {
        return UrssafRequestRejected::withErrors($operation, 400, $errors, ErrorClassifier::classify($errors));
    }

    private static function accountError(ApiErrorCode $code): ApiErrors
    {
        if ($code === ApiErrorCode::BirthDateMismatch) {
            return ApiErrors::of(ApiError::of(
                $code->value,
                'La date de naissance et le numéro Pajemploi ne correspondent pas',
                'La date de naissance correspondant au numéro de Pajemploi de l\'employeur est différente de la date de naissance fournie',
            ));
        }
        if ($code === ApiErrorCode::EmployerMissing) {
            return ApiErrors::of(ApiError::of(
                $code->value,
                'Employeur inexistant',
                'Aucun employeur n\'a été trouvé avec ces informations',
            ));
        }

        return ApiErrors::of(ApiError::of(
            $code->value,
            'Aucun employeur trouvé pour ce numéro',
            'L\'identifiant de l\'employeur ne correspond à aucun employeur connu dans le SI Pajemploi',
        ));
    }

    private static function invalidParameter(): ApiErrors
    {
        return ApiErrors::of(ApiError::of(
            ApiErrorCode::InvalidParameter->value,
            'Un des paramètres de votre demande est invalide',
            'Le salarié est inconnu et aucune donnée de création n\'a été fournie',
        ));
    }

    private static function mandateMissing(): ApiErrors
    {
        return ApiErrors::of(ApiError::of(
            ApiErrorCode::MandateMissing->value,
            'Mandat inexistant',
            'Il n\'existe aucun mandat pour ce PE et ce partenaire',
        ));
    }

    private static function mandateHeldElsewhere(): ApiErrors
    {
        return ApiErrors::of(ApiError::of(
            ApiErrorCode::MandateHeldElsewhere->value,
            'Le compte est déjà mandaté',
            'Il existe déjà un autre établissement mandataire pour ce numéro de compte',
        ));
    }

    private static function duplicateDeclaration(Declaration $declaration): ApiErrors
    {
        return $declaration->activityType() === DeclarationActivity::Childminder
            ? ApiErrors::of(ApiError::of(
                ApiErrorCode::ChildminderDeclarationExists->value,
                'Déclaration existante sur la période',
                'Une déclaration existe déjà pour ce salarié, ce mode de garde, cette période d\'emploi et cet enfant',
            ))
            : ApiErrors::of(ApiError::of(
                ApiErrorCode::HomeCareDeclarationExists->value,
                'Déclaration existante sur la période',
                'Un seul volet social est possible par salarié et par mois',
            ));
    }
}
