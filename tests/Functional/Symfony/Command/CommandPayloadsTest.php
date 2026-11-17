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
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Command\CommandPayloads;

/**
 * @mago-expect lint:too-many-methods
 */
final class CommandPayloadsTest extends TestCase
{
    private static function input(?string $path): ArrayInput
    {
        $command = new Command('probe');
        CommandPayloads::add($command, 'payload', 'A payload');

        return new ArrayInput($path === null ? [] : ['--payload' => $path], $command->getDefinition());
    }

    public function testTheOptionIsDeclared(): void
    {
        $command = new Command('probe');
        CommandPayloads::add($command, 'payload', 'The payload file');

        static::assertTrue($command->getDefinition()->getOption('payload')->isValueRequired());
        static::assertSame('The payload file', $command->getDefinition()->getOption('payload')->getDescription());
    }

    public function testAPayloadIsNullWhenNotGiven(): void
    {
        static::assertNull(CommandPayloads::read(self::input(null), 'payload'));
    }

    public function testAJsonObjectIsRead(): void
    {
        $payload = CommandPayloads::read(self::input(Fields::payload('childminder_estimate.json')), 'payload');

        static::assertSame(['employer_postal_code' => '94022', 'net_monthly_salary' => '500.00', 'children_count' => 2, 'activity_days' => 20], $payload);
    }

    public function testAJsonListIsRead(): void
    {
        $payload = CommandPayloads::read(self::input(Fields::payload('children.json')), 'payload');

        static::assertIsArray($payload);
        static::assertCount(2, $payload);
    }

    public function testARequiredPayloadIsReadOrRefused(): void
    {
        static::assertCount(4, CommandPayloads::require(self::input(Fields::payload('childminder_estimate.json')), 'payload'));

        $this->expectException(InvalidOptionException::class);
        $this->expectExceptionMessage('The --payload option is required.');

        CommandPayloads::require(self::input(null), 'payload');
    }

    #[DataProvider('unusableFiles')]
    public function testAnUnusableFileIsRefusedWithoutItsContent(string $name, string $message): void
    {
        try {
            CommandPayloads::read(self::input(Fields::payload($name)), 'payload');
            static::fail('The file is unusable.');
        } catch (InvalidOptionException $failure) {
            static::assertSame($message, $failure->getMessage());
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function unusableFiles(): iterable
    {
        yield 'missing' => ['missing.json', 'The --payload file cannot be read, or is larger than 1 MiB.'];
        yield 'invalid JSON' => ['invalid.json', 'The --payload file is not valid JSON.'];
        yield 'scalar' => ['scalar.json', 'The --payload file must hold a JSON object or list.'];
        yield 'directory' => ['', 'The --payload file cannot be read, or is larger than 1 MiB.'];
    }

    public function testAFileOverTheLimitIsRefused(): void
    {
        $path = Fields::payload('too_large.json');
        file_put_contents($path, '[' . str_repeat('0,', 524_289) . '0]');

        try {
            CommandPayloads::read(self::input($path), 'payload');
            static::fail('The file is too large.');
        } catch (InvalidOptionException $failure) {
            static::assertSame('The --payload file cannot be read, or is larger than 1 MiB.', $failure->getMessage());
        } finally {
            unlink($path);
        }
    }

    public function testAFileOfTheLimitIsRead(): void
    {
        $path = Fields::payload('at_the_limit.json');
        file_put_contents($path, '[' . str_repeat('0,', 524_286) . '0] ');
        static::assertSame(1_048_576, filesize($path));

        try {
            static::assertCount(524_287, CommandPayloads::require(self::input($path), 'payload'));
        } finally {
            unlink($path);
        }
    }

    public function testAnUnreadableFileIsRefused(): void
    {
        if (posix_geteuid() === 0) {
            static::markTestSkipped('A superuser reads any file.');
        }
        $path = Fields::payload('unreadable.json');
        file_put_contents($path, '{}');
        chmod($path, 0o000);

        try {
            CommandPayloads::read(self::input($path), 'payload');
            static::fail('The file is unreadable.');
        } catch (InvalidOptionException $failure) {
            static::assertSame('The --payload file cannot be read, or is larger than 1 MiB.', $failure->getMessage());
        } finally {
            unlink($path);
        }
    }

    public function testTheDepthOfAFileIsLimited(): void
    {
        $path = Fields::payload('deep.json');
        file_put_contents($path, str_repeat('[', 31) . str_repeat(']', 31));

        try {
            static::assertIsArray(CommandPayloads::read(self::input($path), 'payload'));
            file_put_contents($path, str_repeat('[', 32) . str_repeat(']', 32));
            $this->expectException(InvalidOptionException::class);
            $this->expectExceptionMessage('The --payload file is not valid JSON.');

            CommandPayloads::read(self::input($path), 'payload');
        } finally {
            unlink($path);
        }
    }

    public function testAnEmptyFileIsNotJson(): void
    {
        $path = Fields::payload('empty.json');
        file_put_contents($path, '');
        $this->expectException(InvalidOptionException::class);
        $this->expectExceptionMessage('The --payload file is not valid JSON.');

        try {
            CommandPayloads::read(self::input($path), 'payload');
        } finally {
            unlink($path);
        }
    }
}
