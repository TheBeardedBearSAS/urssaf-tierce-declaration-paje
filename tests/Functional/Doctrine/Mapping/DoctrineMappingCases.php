<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Doctrine\Mapping;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerStage;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateState;

/**
 * Data providers of {@see DoctrineMappingTest}: the case names understood by {@see DoctrineMappingSamples}.
 */
final class DoctrineMappingCases
{
    private function __construct() {}

    /**
     * @return iterable<string, array{string}> case names of {@see DoctrineMappingSamples::account()}
     */
    public static function accounts(): iterable
    {
        yield 'tracked, never checked' => ['tracked'];
        yield 'imported' => ['imported'];
        yield 'verified, documented state' => ['verified'];
        yield 'verified, unknown raw state' => ['verified-unknown'];
        foreach (EmployerStage::cases() as $stage) {
            yield 'stage ' . $stage->value => ['stage:' . $stage->value];
        }
    }

    /**
     * @return iterable<string, array{string}> case names of {@see DoctrineMappingSamples::mandate()}
     */
    public static function mandates(): iterable
    {
        yield 'imported' => ['imported'];
        foreach (MandateState::cases() as $state) {
            yield 'state ' . $state->value => ['state:' . $state->value];
        }
    }

    /**
     * @return iterable<string, array{string}> case names of {@see DoctrineMappingSamples::record()}
     */
    public static function records(): iterable
    {
        foreach (DeclarationState::cases() as $state) {
            yield 'state ' . $state->value => ['state:' . $state->value];
        }
        yield 'outcome unknown, stale submission' => ['unknown-stale'];
        yield 'home care declaration, employee number' => ['home-care'];
        yield 'employee referenced by NIR' => ['nir'];
        yield 'employee referenced by administrative data' => ['administrative'];
        yield 'linked to an employer account' => ['with-account'];
    }
}
