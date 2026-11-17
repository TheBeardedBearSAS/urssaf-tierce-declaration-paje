<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Diagnostic;

use Closure;
use Override;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\ApiConfiguration;
use ValueError;

/**
 * The options resolved at run time (environment, base URI, headers, safety margin) are valid: an invalid value is a
 * failed check instead of a stack trace. The configuration is built by a closure, inside the check.
 */
final readonly class ConfigurationCheck implements InstallationCheck
{
    private const NAME = 'configuration';

    /**
     * @param Closure(): ApiConfiguration $configuration builds the configuration (it may throw for an invalid option)
     */
    public function __construct(private Closure $configuration) {}

    #[Override]
    public function run(CheckContext $context): array
    {
        // @mago-expect analysis:avoid-catching-error Environment::from() throws a ValueError for an unknown environment: it is reported by the check, not a crash.
        try {
            $configuration = ($this->configuration)();
        } catch (ValueError|InvalidValue $failure) {
            return [new CheckResult(self::NAME, '', CheckStatus::Failure, $failure->getMessage())];
        }

        return [new CheckResult(self::NAME, '', CheckStatus::Ok, \sprintf('%s: %s', $configuration->environment()->value, $configuration->baseUri()))];
    }
}
