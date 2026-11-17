<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Child\Input;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Child\Input\ChildFactory;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Declaration\DeclarationSamples;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\DomainAssertions;

final class ChildFactoryTest extends TestCase
{
    public function testFromInput(): void
    {
        $child = (new ChildFactory())->fromInput(['birth_date' => '2015-01-12', 'last_name' => "L'HOMME", 'first_name' => 'JEAN-MICHEL']);

        static::assertTrue($child->equals(DeclarationSamples::child()));
        static::assertSame([], DomainAssertions::summary((new ChildFactory())->validate(['birth_date' => '2015-01-12', 'last_name' => "L'HOMME", 'first_name' => 'JEAN-MICHEL'])));
    }

    public function testEveryMissingFieldIsReported(): void
    {
        static::assertSame(
            ['error birth_date birth_date.required', 'error last_name last_name.required', 'error first_name first_name.required'],
            DomainAssertions::summary((new ChildFactory())->validate([])),
        );
    }

    public function testEveryInvalidFieldIsReported(): void
    {
        static::assertSame(
            ['error birth_date calendar_date.invalid_date', 'error last_name last_name.invalid_format', 'error first_name first_name.too_long'],
            DomainAssertions::summary((new ChildFactory())->validate(['birth_date' => '2015-02-30', 'last_name' => 'Dupont', 'first_name' => str_repeat('A', 31)])),
        );
    }

    public function testASingleInvalidPartIsEnoughToFail(): void
    {
        $valid = ['birth_date' => '2015-01-12', 'last_name' => 'DUPONT', 'first_name' => 'PAUL'];
        $factory = new ChildFactory();

        foreach (['birth_date' => 'x', 'last_name' => 'd', 'first_name' => 'p'] as $key => $invalid) {
            $result = $factory->validate([$key => $invalid] + $valid);
            static::assertCount(1, $result->errors(), $key);
            static::assertSame($key, $result->errors()[0]->path());
        }
    }

    public function testRawInputIsSensitive(): void
    {
        DomainAssertions::assertSensitiveParameters(ChildFactory::class, 'validate', ['input']);
        DomainAssertions::assertSensitiveParameters(ChildFactory::class, 'fromInput', ['input']);
    }
}
