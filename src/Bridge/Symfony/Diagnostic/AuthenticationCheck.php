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
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\CredentialsProvider;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\UrssafException;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Http\OAuth2TokenProvider;
use ValueError;

/**
 * URSSAF grants an access token to every tenant: any cached token is dropped first, so that it never hides refused
 * credentials. The token provider given here must use a token store of its own (the bundle gives it an in-memory one):
 * the tokens of the application are left untouched.
 *
 * The provider is built by a closure inside the check, so that an invalid configuration is reported by the
 * "configuration" check instead of preventing the command from running. The probe requests a token for method 020, a
 * read (the scope is the same for every operation).
 */
final readonly class AuthenticationCheck implements InstallationCheck
{
    private const NAME = 'authentication';

    /**
     * @param Closure(): OAuth2TokenProvider $tokens
     */
    public function __construct(private CredentialsProvider $credentials, private Closure $tokens) {}

    #[Override]
    public function run(CheckContext $context): array
    {
        if (!$context->remoteCallsAllowed) {
            return [new CheckResult(self::NAME, '', CheckStatus::Skipped, 'Not checked (--skip-remote).')];
        }

        return array_map($this->check(...), $context->tenants);
    }

    private function check(TenantId $tenantId): CheckResult
    {
        try {
            $credentials = $this->credentials->forTenant($tenantId);
            $tokens = $this->provider();
            if (!$tokens instanceof OAuth2TokenProvider) {
                return new CheckResult(self::NAME, $tenantId->value(), CheckStatus::Failure, 'Not checked: the configuration is invalid (see the "configuration" check).');
            }
            $tokens->invalidate($credentials);
            $tokens->accessToken($credentials, Operation::VerifyEmployer);
        } catch (UrssafException $failure) {
            return new CheckResult(self::NAME, $tenantId->value(), CheckStatus::Failure, $failure->getMessage());
        }

        return new CheckResult(self::NAME, $tenantId->value(), CheckStatus::Ok, 'Access token granted by URSSAF.');
    }

    /**
     * Null when the configuration is invalid (unknown environment, base URI, header...).
     */
    private function provider(): ?OAuth2TokenProvider
    {
        // @mago-expect analysis:avoid-catching-error Environment::from() throws a ValueError for an unknown environment: it is reported by the check, not a crash.
        try {
            return ($this->tokens)();
        } catch (ValueError|InvalidValue) {
            return null;
        }
    }
}
