<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Benchmark;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Child\Child;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\CalculationMode;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\ChildminderDeclaration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\HomeCareDeclaration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeCreation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Estimate\ChildminderEstimateRequest;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Estimate\HomeCareEstimateRequest;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Estimate\YoungestChildAge;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Amount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\HalfDays;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\PostalCode;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\AssociateEmployeeRequestMapper;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\DeclarationRequestMapper;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\EstimateRequestMapper;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\JsonEncoder;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\VerifyChildrenRequestMapper;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\EmployeeWireSamples;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Wire\DeclarationWireSamples;

/**
 * Encoding of the request bodies sent to URSSAF (children verification 022, association 023, estimates 040 / 041 and
 * declarations 050 / 051), down to the JSON string. The children list has the shape of `verify_children_request.json`.
 */
final readonly class WireEncodingBench
{
    private ChildminderDeclaration $childminder;

    private HomeCareDeclaration $homeCare;

    /** @var list<Child> */
    private array $children;

    private EmployeeCreation $creation;

    private ChildminderEstimateRequest $childminderEstimate;

    private HomeCareEstimateRequest $homeCareEstimate;

    public function __construct()
    {
        $this->childminder = DeclarationWireSamples::declareChildminder();
        $this->homeCare = DeclarationWireSamples::homeCare();
        $this->children = [EmployeeWireSamples::child()];
        $this->creation = EmployeeWireSamples::creation();
        $this->childminderEstimate = ChildminderEstimateRequest::of(
            PostalCode::fromString('75000'),
            PostalCode::fromString('75000'),
            Amount::fromDecimalString('165'),
            1,
            10,
            HalfDays::fromHalfDays(2),
            0,
            0,
        );
        $this->homeCareEstimate = HomeCareEstimateRequest::of(
            PostalCode::fromString('75000'),
            PostalCode::fromString('75000'),
            Amount::fromDecimalString('165'),
            CalculationMode::real(),
            10,
            0,
            0,
            YoungestChildAge::underThree(),
            true,
        );
    }

    public function benchEncodeAChildminderDeclaration(): void
    {
        JsonEncoder::encode(DeclarationRequestMapper::body($this->childminder));
    }

    public function benchEncodeAHomeCareDeclaration(): void
    {
        JsonEncoder::encode(DeclarationRequestMapper::body($this->homeCare));
    }

    public function benchEncodeAnAssociation(): void
    {
        JsonEncoder::encode(AssociateEmployeeRequestMapper::body($this->creation));
    }

    public function benchEncodeAChildminderEstimate(): void
    {
        JsonEncoder::encode(EstimateRequestMapper::body($this->childminderEstimate));
    }

    public function benchEncodeAHomeCareEstimate(): void
    {
        JsonEncoder::encode(EstimateRequestMapper::body($this->homeCareEstimate));
    }

    public function benchEncodeAChildrenVerification(): void
    {
        JsonEncoder::encode(VerifyChildrenRequestMapper::body($this->children));
    }
}
