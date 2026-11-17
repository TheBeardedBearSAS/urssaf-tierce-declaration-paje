<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * The JSON files that carry the personal parts of an input (a declaration, an estimate, children, an employee identity).
 * They are given by path, never inline (shell history, process list, cron logs: ADR 0007, ADR 0013). Nothing of the file
 * is ever echoed: an unreadable or invalid file is reported without its content.
 */
final class CommandPayloads
{
    /** A payload is a few kilobytes: a larger file is not one. */
    private const MAX_BYTES = 1_048_576;
    private const MAX_DEPTH = 32;

    public static function add(Command $command, string $option, string $description): void
    {
        $command->addOption($option, null, InputOption::VALUE_REQUIRED, $description);
    }

    /**
     * The decoded payload of an option, null when it is not given.
     *
     * @return array<array-key, mixed>|null
     *
     * @throws InvalidOptionException for a file that cannot be read, is larger than 1 MiB or does not hold a JSON object or list
     */
    public static function read(InputInterface $input, string $option): ?array
    {
        $path = CommandOptions::text($input, $option);
        if ($path === null) {
            return null;
        }
        $size = is_file($path) && is_readable($path) ? filesize($path) : null;
        $content = \is_int($size) && $size <= self::MAX_BYTES ? file_get_contents($path) : null;
        if (!\is_string($content)) {
            throw new InvalidOptionException(\sprintf('The --%s file cannot be read, or is larger than 1 MiB.', $option));
        }
        // @mago-expect analysis:mixed-assignment The decoded JSON is untyped: it is narrowed on the next lines.
        $payload = json_decode($content, true, self::MAX_DEPTH);
        if (json_last_error() !== \JSON_ERROR_NONE) {
            throw new InvalidOptionException(\sprintf('The --%s file is not valid JSON.', $option));
        }

        return \is_array($payload) ? $payload : throw new InvalidOptionException(\sprintf('The --%s file must hold a JSON object or list.', $option));
    }

    /**
     * Same, the option being required.
     *
     * @return array<array-key, mixed>
     *
     * @throws InvalidOptionException when the option is not given, or for an unusable file
     */
    public static function require(InputInterface $input, string $option): array
    {
        return self::read($input, $option) ?? throw new InvalidOptionException(\sprintf('The --%s option is required.', $option));
    }
}
