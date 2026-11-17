<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\OutcomeUnknown;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\PajeGateway;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Child\Child;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Child\ChildCheck;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\ChildminderDeclaration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationResult;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\HomeCareDeclaration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\PreDeclarationResult;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeAccount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeAssociation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeCreation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeIdentification;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerVerification;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Estimate\ChildminderEstimateRequest;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Estimate\ContributionEstimate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Estimate\HomeCareEstimateRequest;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Http\ApiRequest;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Http\ApiResponse;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Http\JsonApiClient;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\ApiPaths;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\AssociateEmployeeRequestMapper;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\AssociateEmployeeResponseMapper;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\ContributionEstimateResponseMapper;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\DeclarationRequestMapper;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\DeclarationResponseMapper;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\EstimateRequestMapper;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\MandateRequestMapper;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\MandateResponseMapper;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\Payload;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\PreDeclarationResponseMapper;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\VerifyChildrenRequestMapper;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\VerifyChildrenResponseMapper;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\VerifyEmployeeRequestMapper;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\VerifyEmployeeResponseMapper;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\VerifyEmployerRequestMapper;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\VerifyEmployerResponseMapper;

/**
 * HTTP implementation of the twelve operations of the API Tierce Déclaration PAJE (020 to 051). Build it with
 * {@see UrssafGatewayFactory}.
 *
 * Status-level failures (transport, 4xx, 5xx, authentication) are mapped by the {@see JsonApiClient} (ADR 0002, ADR 0003).
 * This class only decides what an unreadable 2xx answer means (ADR 0003): on a safe operation, or on an unspecified
 * write that the configuration lists as safe to retry, it is an {@see UnexpectedUrssafResponse}; on every other
 * operation (050, 051 and the unlisted 023, 030, 031) URSSAF processed the call, so it is an {@see OutcomeUnknown}
 * that must not be resent.
 *
 * One method per operation of the {@see PajeGateway} port, hence the method count.
 *
 * @mago-expect lint:too-many-methods
 */
final readonly class UrssafHttpGateway implements PajeGateway
{
    public function __construct(
        private JsonApiClient $client,
        private ApiConfiguration $configuration,
    ) {}

    public function verifyEmployer(TenantId $tenantId, #[SensitiveParameter] EmployerReference $employer): EmployerVerification
    {
        $response = $this->client->send($tenantId, ApiRequest::get(
            Operation::VerifyEmployer,
            ApiPaths::verifyEmployer($employer->number()),
            VerifyEmployerRequestMapper::query($employer),
        ));

        return $this->read(
            Operation::VerifyEmployer,
            $response,
            VerifyEmployerResponseMapper::map(...),
        );
    }

    public function verifyEmployee(TenantId $tenantId, #[SensitiveParameter] EmployeeIdentification $employee): EmployeeAccount
    {
        $response = $this->client->send($tenantId, ApiRequest::post(
            Operation::VerifyEmployee,
            ApiPaths::verifyEmployee(),
            VerifyEmployeeRequestMapper::query($employee),
            VerifyEmployeeRequestMapper::body($employee),
        ));

        return $this->read(
            Operation::VerifyEmployee,
            $response,
            VerifyEmployeeResponseMapper::map(...),
        );
    }

    /**
     * @param list<Child> $children
     *
     * @return list<ChildCheck>
     */
    public function verifyChildren(
        TenantId $tenantId,
        #[SensitiveParameter]
        EmployerReference $employer,
        #[SensitiveParameter]
        array $children,
    ): array {
        $response = $this->client->send($tenantId, ApiRequest::post(
            Operation::VerifyChildren,
            ApiPaths::verifyChildren($employer->number()),
            VerifyChildrenRequestMapper::query($employer),
            VerifyChildrenRequestMapper::body($children),
        ));

        return $this->read(
            Operation::VerifyChildren,
            $response,
            VerifyChildrenResponseMapper::map(...),
        );
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
        $response = $this->client->send($tenantId, ApiRequest::post(
            Operation::AssociateEmployee,
            ApiPaths::associateEmployee($employer->number()),
            AssociateEmployeeRequestMapper::query($employer, $employee),
            AssociateEmployeeRequestMapper::body($creation),
        ));

        return $this->read(
            Operation::AssociateEmployee,
            $response,
            AssociateEmployeeResponseMapper::map(...),
        );
    }

    public function registerMandate(TenantId $tenantId, #[SensitiveParameter] EmployerReference $employer): void
    {
        $response = $this->client->send($tenantId, ApiRequest::post(
            Operation::RegisterMandate,
            ApiPaths::registerMandate(),
            MandateRequestMapper::query($employer),
        ));

        $this->readEmpty(Operation::RegisterMandate, $response);
    }

    public function cancelMandate(TenantId $tenantId, #[SensitiveParameter] EmployerReference $employer): void
    {
        $response = $this->client->send($tenantId, ApiRequest::delete(
            Operation::CancelMandate,
            ApiPaths::cancelMandate(),
            MandateRequestMapper::query($employer),
        ));

        $this->readEmpty(Operation::CancelMandate, $response);
    }

    public function estimateChildminder(TenantId $tenantId, ChildminderEstimateRequest $request): ContributionEstimate
    {
        $response = $this->client->send($tenantId, ApiRequest::post(
            Operation::EstimateChildminder,
            ApiPaths::estimateChildminder(),
            [],
            EstimateRequestMapper::body($request),
        ));

        return $this->read(
            Operation::EstimateChildminder,
            $response,
            ContributionEstimateResponseMapper::map(...),
        );
    }

    public function estimateHomeCare(TenantId $tenantId, HomeCareEstimateRequest $request): ContributionEstimate
    {
        $response = $this->client->send($tenantId, ApiRequest::post(
            Operation::EstimateHomeCare,
            ApiPaths::estimateHomeCare(),
            [],
            EstimateRequestMapper::body($request),
        ));

        return $this->read(
            Operation::EstimateHomeCare,
            $response,
            ContributionEstimateResponseMapper::map(...),
        );
    }

    public function preDeclareChildminder(
        TenantId $tenantId,
        #[SensitiveParameter]
        EmployerReference $employer,
        #[SensitiveParameter]
        ChildminderDeclaration $declaration,
    ): PreDeclarationResult {
        $response = $this->client->send($tenantId, ApiRequest::post(
            Operation::PreDeclareChildminder,
            ApiPaths::preDeclareChildminder($employer->number()),
            DeclarationRequestMapper::query($employer),
            DeclarationRequestMapper::body($declaration),
        ));

        return $this->read(
            Operation::PreDeclareChildminder,
            $response,
            PreDeclarationResponseMapper::map(...),
        );
    }

    public function preDeclareHomeCare(
        TenantId $tenantId,
        #[SensitiveParameter]
        EmployerReference $employer,
        #[SensitiveParameter]
        HomeCareDeclaration $declaration,
    ): PreDeclarationResult {
        $response = $this->client->send($tenantId, ApiRequest::post(
            Operation::PreDeclareHomeCare,
            ApiPaths::preDeclareHomeCare($employer->number()),
            DeclarationRequestMapper::query($employer),
            DeclarationRequestMapper::body($declaration),
        ));

        return $this->read(
            Operation::PreDeclareHomeCare,
            $response,
            PreDeclarationResponseMapper::map(...),
        );
    }

    public function declareChildminder(
        TenantId $tenantId,
        #[SensitiveParameter]
        EmployerReference $employer,
        #[SensitiveParameter]
        ChildminderDeclaration $declaration,
    ): DeclarationResult {
        $response = $this->client->send($tenantId, ApiRequest::post(
            Operation::DeclareChildminder,
            ApiPaths::declareChildminder($employer->number()),
            DeclarationRequestMapper::query($employer),
            DeclarationRequestMapper::body($declaration),
        ));

        return $this->read(
            Operation::DeclareChildminder,
            $response,
            DeclarationResponseMapper::map(...),
        );
    }

    public function declareHomeCare(
        TenantId $tenantId,
        #[SensitiveParameter]
        EmployerReference $employer,
        #[SensitiveParameter]
        HomeCareDeclaration $declaration,
    ): DeclarationResult {
        $response = $this->client->send($tenantId, ApiRequest::post(
            Operation::DeclareHomeCare,
            ApiPaths::declareHomeCare($employer->number()),
            DeclarationRequestMapper::query($employer),
            DeclarationRequestMapper::body($declaration),
        ));

        return $this->read(
            Operation::DeclareHomeCare,
            $response,
            DeclarationResponseMapper::map(...),
        );
    }

    /**
     * 030 / 031: the answer must carry no payload (blank body or `{}`).
     */
    private function readEmpty(Operation $operation, ApiResponse $response): void
    {
        $this->guard($operation, $response, static function () use ($response): void {
            MandateResponseMapper::assertEmpty($response->body());
        });
    }

    /**
     * Decodes and maps an already received 2xx answer. The only ADR 0003 decision of the gateway: when the answer
     * cannot be read, an operation that may be resent reports the unexpected response, any other one (URSSAF did
     * process it) an unknown outcome. Neither the body nor the reason of the failure leaves this method (ADR 0007).
     *
     * @template T
     *
     * @param-immediately-invoked-callable $map
     * @param callable(Payload): T $map
     *
     * @return T
     *
     * @throws UnexpectedUrssafResponse when the operation may be resent
     * @throws OutcomeUnknown           otherwise
     */
    private function read(Operation $operation, ApiResponse $response, callable $map): mixed
    {
        return $this->guard($operation, $response, static fn(): mixed => $map($response->payload()));
    }

    /**
     * @template T
     *
     * @param-immediately-invoked-callable $decode
     * @param callable(): T $decode
     *
     * @return T
     *
     * @throws UnexpectedUrssafResponse when the operation may be resent
     * @throws OutcomeUnknown           otherwise
     */
    private function guard(Operation $operation, ApiResponse $response, callable $decode): mixed
    {
        try {
            return $decode();
        } catch (UnexpectedUrssafResponse $unreadable) {
            if ($this->configuration->isSafeToRetry($operation)) {
                throw $unreadable;
            }

            throw OutcomeUnknown::afterUnreadableResponse($operation, $response->status(), $unreadable);
        }
    }
}
