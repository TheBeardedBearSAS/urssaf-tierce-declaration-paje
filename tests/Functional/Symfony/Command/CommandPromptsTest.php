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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Exception\MissingInputException;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Command\CommandPrompts;

final class CommandPromptsTest extends TestCase
{
    public function testTheAnswerIsReadFromTheInputWithoutBeingEchoed(): void
    {
        $input = self::input("secret-value\n");
        $output = new BufferedOutput();

        static::assertSame('secret-value', CommandPrompts::hiddenValue($input, $output, new QuestionHelper(), 'Employee NIR'));

        static::assertSame("Employee NIR: \n", $output->fetch());
    }

    public function testAnEmptyAnswerIsReturnedAsIs(): void
    {
        static::assertSame('', CommandPrompts::hiddenValue(self::input("\n"), new BufferedOutput(), new QuestionHelper(), 'Employee NIR'));
    }

    public function testANonInteractiveRunIsRefused(): void
    {
        $input = self::input("secret-value\n");
        $input->setInteractive(false);
        $this->expectException(InvalidOptionException::class);
        $this->expectExceptionMessage('Employee NIR is read from a hidden prompt: run the command interactively.');

        CommandPrompts::hiddenValue($input, new BufferedOutput(), new QuestionHelper(), 'Employee NIR');
    }

    public function testAMissingAnswerIsRefused(): void
    {
        try {
            CommandPrompts::hiddenValue(self::input(''), new BufferedOutput(), new QuestionHelper(), 'Employee NIR');
            static::fail('A missing answer must be refused.');
        } catch (InvalidOptionException $failure) {
            static::assertSame('Employee NIR was not given.', $failure->getMessage());
            static::assertSame(0, $failure->getCode());
            static::assertInstanceOf(MissingInputException::class, $failure->getPrevious());
        }
    }

    private static function input(string $stream): ArrayInput
    {
        $input = new ArrayInput([]);
        $handle = fopen('php://memory', 'r+');
        \assert($handle !== false, 'The memory stream is available.');
        fwrite($handle, $stream);
        rewind($handle);
        $input->setStream($handle);

        return $input;
    }
}
