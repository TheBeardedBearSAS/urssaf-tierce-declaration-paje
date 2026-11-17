<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Command;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Input\ArrayInput;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Command\CommandSelectors;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\PajeEmployerNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\ExternalReference;

/**
 * @mago-expect lint:too-many-methods
 */
final class CommandSelectorsTest extends TestCase
{
    /**
     * @param array<string, string> $values
     */
    private static function input(array $values): ArrayInput
    {
        $command = new Command('probe');
        CommandSelectors::addEmployerSelector($command);
        CommandSelectors::addDeclarationSelector($command);

        return new ArrayInput($values, $command->getDefinition());
    }

    public function testTheOptionsAreDeclared(): void
    {
        $command = new Command('probe');
        CommandSelectors::addEmployerSelector($command);
        CommandSelectors::addDeclarationSelector($command);

        foreach (['employer', 'employer-number', 'account-id', 'declaration', 'record-id'] as $name) {
            static::assertTrue($command->getDefinition()->getOption($name)->isValueRequired(), $name);
        }
    }

    public function testAnEmployerIsDesignatedByItsExternalReference(): void
    {
        $reference = CommandSelectors::employerSelector(self::input(['--employer' => 'employer-1']))->reference;

        static::assertInstanceOf(ExternalReference::class, $reference);
        static::assertSame('employer-1', $reference->value());
    }

    public function testAnEmployerIsDesignatedByItsPajemploiNumber(): void
    {
        $reference = CommandSelectors::employerSelector(self::input(['--employer-number' => 'Y1869005780009']))->reference;

        static::assertInstanceOf(PajeEmployerNumber::class, $reference);
        static::assertSame('Y1869005780009', $reference->value());
    }

    public function testAnEmployerIsDesignatedByItsAccountId(): void
    {
        $reference = CommandSelectors::employerSelector(self::input(['--account-id' => 'account-1']))->reference;

        static::assertInstanceOf(EmployerAccountId::class, $reference);
        static::assertSame('account-1', $reference->value());
    }

    public function testNoEmployerSelectorIsNullWhenOptional(): void
    {
        static::assertNull(CommandSelectors::optionalEmployerSelector(self::input([])));
    }

    /**
     * @param array<string, string> $values
     */
    #[DataProvider('invalidEmployerSelectors')]
    public function testAnEmployerSelectorIsExactlyOne(array $values): void
    {
        $this->expectException(InvalidOptionException::class);
        $this->expectExceptionMessage('Give one of --employer, --employer-number or --account-id.');

        CommandSelectors::employerSelector(self::input($values));
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function invalidEmployerSelectors(): iterable
    {
        yield 'none' => [[]];
        yield 'two' => [['--employer' => 'employer-1', '--employer-number' => 'Y1869005780009']];
        yield 'reference and account' => [['--employer' => 'employer-1', '--account-id' => 'a']];
        yield 'number and account' => [['--employer-number' => 'Y1869005780009', '--account-id' => 'a']];
        yield 'three' => [['--employer' => 'employer-1', '--employer-number' => 'Y1869005780009', '--account-id' => 'a']];
    }

    public function testSeveralOptionalEmployerSelectorsAreRefused(): void
    {
        $this->expectException(InvalidOptionException::class);

        CommandSelectors::optionalEmployerSelector(self::input(['--employer' => 'employer-1', '--account-id' => 'a']));
    }

    public function testAnInvalidEmployerNumberIsAnInvalidValue(): void
    {
        $this->expectException(InvalidValue::class);

        CommandSelectors::employerSelector(self::input(['--employer-number' => 'x']));
    }

    public function testADeclarationIsDesignatedByItsExternalReference(): void
    {
        $reference = CommandSelectors::declarationSelector(self::input(['--declaration' => 'declaration-1']))->reference;

        static::assertInstanceOf(ExternalReference::class, $reference);
        static::assertSame('declaration-1', $reference->value());
    }

    public function testADeclarationIsDesignatedByItsRecordId(): void
    {
        $reference = CommandSelectors::declarationSelector(self::input(['--record-id' => 'record-1']))->reference;

        static::assertInstanceOf(DeclarationRecordId::class, $reference);
        static::assertSame('record-1', $reference->value());
    }

    /**
     * @param array<string, string> $values
     */
    #[DataProvider('invalidDeclarationSelectors')]
    public function testADeclarationSelectorIsExactlyOne(array $values): void
    {
        $this->expectException(InvalidOptionException::class);
        $this->expectExceptionMessage('Give one of --declaration or --record-id.');

        CommandSelectors::declarationSelector(self::input($values));
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function invalidDeclarationSelectors(): iterable
    {
        yield 'none' => [[]];
        yield 'both' => [['--declaration' => 'declaration-1', '--record-id' => 'record-1']];
    }
}
