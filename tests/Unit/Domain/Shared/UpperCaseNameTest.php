<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Shared;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\UpperCaseName;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\DomainAssertions;

final class UpperCaseNameTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function provideValidNames(): iterable
    {
        yield 'empty (no minLength in the Swagger)' => [''];
        yield 'simple' => ['DUPONT'];
        yield 'official example' => ["L'HOMME"];
        yield 'hyphen' => ['JEAN-MICHEL'];
        yield 'space' => ['DE LA FONTAINE'];
        yield 'tab' => ["A\tB"];
        yield 'only separators' => ["' -"];
        yield 'maximum length' => [str_repeat('A', 30)];
    }

    #[DataProvider('provideValidNames')]
    public function testOfKeepsTheValue(string $name): void
    {
        static::assertSame($name, UpperCaseName::of($name, 'child_last_name')->value());
    }

    public function testTooLong(): void
    {
        $exception = DomainAssertions::assertViolationCode('child_last_name.too_long', static fn(): UpperCaseName => UpperCaseName::of(str_repeat('A', 31), 'child_last_name'));

        static::assertSame(['max_length' => 30], $exception->params());
    }

    public function testLengthIsCountedInCharacters(): void
    {
        static::assertSame('employee_first_name.too_long', DomainAssertions::assertViolationCode('employee_first_name.too_long', static fn(): UpperCaseName => UpperCaseName::of(str_repeat('É', 31), 'employee_first_name'))->violationCode());
        static::assertSame('employee_first_name.invalid_format', DomainAssertions::assertViolationCode('employee_first_name.invalid_format', static fn(): UpperCaseName => UpperCaseName::of(str_repeat('É', 30), 'employee_first_name'))->violationCode());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidNames(): iterable
    {
        yield 'lower case' => ['Dupont'];
        yield 'all lower case' => ['dupont'];
        yield 'accent' => ['ÉLODIE'];
        yield 'digit' => ['DUPONT2'];
        yield 'dot' => ['J.'];
        yield 'underscore' => ['A_B'];
        yield 'invalid in the middle' => ['AB1CD'];
        yield 'invalid at the start' => ['1ABC'];
        yield 'invalid at the end' => ['ABC1'];
        yield 'typographic apostrophe' => ['L’HOMME'];
        yield 'comma' => ['A,B'];
    }

    #[DataProvider('provideInvalidNames')]
    public function testInvalidFormat(string $name): void
    {
        $exception = DomainAssertions::assertViolationCode('employee_last_name.invalid_format', static fn(): UpperCaseName => UpperCaseName::of($name, 'employee_last_name'));

        static::assertStringNotContainsString($name, $exception->getMessage());
    }

    public function testDebugInfoIsMasked(): void
    {
        $name = UpperCaseName::of('DUPONT', 'employee_last_name');

        static::assertSame(['value' => '***'], $name->__debugInfo());
        static::assertStringNotContainsString('DUPONT', print_r($name, true));
        static::assertStringNotContainsString('DUPONT', var_export($name->__debugInfo(), true));
        static::assertFalse((new ReflectionClass($name))->hasMethod('__toString'));
    }

    public function testEquality(): void
    {
        static::assertTrue(UpperCaseName::of('DUPONT', 'a')->equals(UpperCaseName::of('DUPONT', 'b')));
        static::assertFalse(UpperCaseName::of('DUPONT', 'a')->equals(UpperCaseName::of('DURAND', 'a')));
    }
}
