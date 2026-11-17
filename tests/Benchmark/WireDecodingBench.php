<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Benchmark;

use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\AssociateEmployeeResponseMapper;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\ContributionEstimateResponseMapper;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\DeclarationResponseMapper;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\ErrorPayloadParser;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\JsonDecoder;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\PreDeclarationResponseMapper;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\VerifyChildrenResponseMapper;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\VerifyEmployeeResponseMapper;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\VerifyEmployerResponseMapper;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\Fixtures;

/**
 * Decoding of the official URSSAF responses, from the raw JSON string: employer (020) and employee (021) verification,
 * children verification (022), association (023), estimate (040 / 041), pre-declaration (045 / 046), declaration
 * (050 / 051) and the two error payload shapes: a list (Swagger) and a single object (mandates)
 * (ADR 0005).
 */
final readonly class WireDecodingBench
{
    private ErrorPayloadParser $errorParser;

    private string $declareResponse;

    private string $preDeclareResponse;

    private string $verifyChildrenResponse;

    private string $associateResponse;

    private string $verifyEmployerResponse;

    private string $verifyEmployeeResponse;

    private string $estimateResponse;

    private string $errorResponse;

    private string $singleObjectErrorResponse;

    public function __construct()
    {
        $this->errorParser = new ErrorPayloadParser();
        $this->declareResponse = Fixtures::load('declare_response_valid');
        $this->preDeclareResponse = Fixtures::load('predeclare_response_valid');
        $this->verifyChildrenResponse = Fixtures::load('verify_children_response');
        $this->associateResponse = Fixtures::load('associate_employee_response');
        $this->verifyEmployerResponse = Fixtures::load('verify_employer_response');
        $this->verifyEmployeeResponse = Fixtures::load('verify_employee_response');
        $this->estimateResponse = Fixtures::load('estimate_response');
        $this->errorResponse = Fixtures::load('error_duplicate_declaration_0070');
        $this->singleObjectErrorResponse = Fixtures::load('error_compte_deja_mandate');
    }

    public function benchDecodeADeclarationResponse(): void
    {
        DeclarationResponseMapper::map(JsonDecoder::decode($this->declareResponse));
    }

    public function benchDecodeAnAssociationResponse(): void
    {
        AssociateEmployeeResponseMapper::map(JsonDecoder::decode($this->associateResponse));
    }

    public function benchDecodeAPreDeclarationResponse(): void
    {
        PreDeclarationResponseMapper::map(JsonDecoder::decode($this->preDeclareResponse));
    }

    public function benchDecodeAChildrenVerificationResponse(): void
    {
        VerifyChildrenResponseMapper::map(JsonDecoder::decode($this->verifyChildrenResponse));
    }

    public function benchDecodeAnEmployerVerificationResponse(): void
    {
        VerifyEmployerResponseMapper::map(JsonDecoder::decode($this->verifyEmployerResponse));
    }

    public function benchDecodeAnEmployeeVerificationResponse(): void
    {
        VerifyEmployeeResponseMapper::map(JsonDecoder::decode($this->verifyEmployeeResponse));
    }

    public function benchDecodeAnEstimateResponse(): void
    {
        ContributionEstimateResponseMapper::map(JsonDecoder::decode($this->estimateResponse));
    }

    public function benchParseAnErrorPayload(): void
    {
        $this->errorParser->parseBody($this->errorResponse);
    }

    public function benchParseASingleObjectErrorPayload(): void
    {
        $this->errorParser->parseBody($this->singleObjectErrorResponse);
    }
}
