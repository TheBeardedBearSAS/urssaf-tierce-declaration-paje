<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Child\Input;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Child\Input\ChildListFactory;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Declaration\DeclarationSamples;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\DomainAssertions;

final class ChildListFactoryTest extends TestCase
{
    public function testFromInputKeepsTheOrder(): void
    {
        $children = (new ChildListFactory())->fromInput([
            ['birth_date' => '2015-01-12', 'last_name' => "L'HOMME", 'first_name' => 'JEAN-MICHEL'],
            ['birth_date' => '2018-03-04', 'last_name' => 'DUPONT', 'first_name' => 'PAUL'],
        ]);

        static::assertCount(2, $children);
        static::assertTrue($children[0]->equals(DeclarationSamples::child()));
        static::assertSame('PAUL', $children[1]->firstName()->value());
    }

    public function testAKeyedListIsReindexed(): void
    {
        $children = (new ChildListFactory())->fromInput(['a' => ['birth_date' => '2015-01-12', 'last_name' => 'A', 'first_name' => 'B']]);

        static::assertSame([0], array_keys($children));
    }

    public function testNoMinimumIsEnforced(): void
    {
        static::assertSame([], (new ChildListFactory())->fromInput([]));
        static::assertSame([], DomainAssertions::summary((new ChildListFactory())->validate([])));
    }

    public function testEveryViolationOfEveryItemIsReportedWithItsIndex(): void
    {
        $result = (new ChildListFactory())->validate([
            ['birth_date' => '2015-01-12', 'last_name' => 'DUPONT', 'first_name' => 'PAUL'],
            [],
            ['birth_date' => 'x', 'last_name' => 'dupont', 'first_name' => 'PAUL'],
            'not a map',
        ]);

        static::assertSame(
            [
                'error children[1].birth_date birth_date.required',
                'error children[1].last_name last_name.required',
                'error children[1].first_name first_name.required',
                'error children[2].birth_date calendar_date.invalid_format',
                'error children[2].last_name last_name.invalid_format',
                'error children[3] children.invalid_type',
            ],
            DomainAssertions::summary($result),
        );
    }

    public function testFromInputThrowsTheViolations(): void
    {
        static::assertSame(
            ['error children[0].last_name last_name.required'],
            DeclarationSamples::violations(static fn(): mixed => (new ChildListFactory())->fromInput([['birth_date' => '2015-01-12', 'first_name' => 'PAUL']])),
        );
    }

    public function testRawInputIsSensitive(): void
    {
        DomainAssertions::assertSensitiveParameters(ChildListFactory::class, 'validate', ['children']);
        DomainAssertions::assertSensitiveParameters(ChildListFactory::class, 'fromInput', ['children']);
    }
}
