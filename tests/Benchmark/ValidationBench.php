<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Benchmark;

use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\EmployeeWireSamples;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Wire\DeclarationWireSamples;

/**
 * Construction (and therefore validation, ADR 0009) of the biggest real cases: the official declarations with every
 * optional field set, and the child list of the verification call (one list shaped like `verify_children_request.json`). PAJE calls are unitary: the official examples are
 * the largest inputs of one call.
 */
final readonly class ValidationBench
{
    public function benchBuildAChildminderDeclarationWithEveryOptionalField(): void
    {
        DeclarationWireSamples::declareChildminder();
    }

    public function benchBuildAHomeCareDeclarationWithEveryOptionalField(): void
    {
        DeclarationWireSamples::homeCare();
    }

    public function benchBuildTheChildrenOfAVerification(): void
    {
        EmployeeWireSamples::child();
    }

    public function benchBuildAnEmployeeCreation(): void
    {
        EmployeeWireSamples::creation();
    }
}
