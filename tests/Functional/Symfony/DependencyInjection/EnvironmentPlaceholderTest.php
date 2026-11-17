<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\DependencyInjection;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\DependencyInjection\ServiceIds;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Environment;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Support\Harness;

/**
 * The documented configuration reads the environment from an environment variable: it is resolved at runtime.
 */
final class EnvironmentPlaceholderTest extends TestCase
{
    public function testTheEnvironmentMayComeFromAnEnvironmentVariable(): void
    {
        $_ENV['URSSAF_TEST_ENVIRONMENT'] = 'production';
        $_SERVER['URSSAF_TEST_ENVIRONMENT'] = 'production';
        try {
            $configuration = Harness::boot(['environment' => '%env(URSSAF_TEST_ENVIRONMENT)%'])
                ->privateService(ServiceIds::API_CONFIGURATION, ApiConfiguration::class);
        } finally {
            unset($_ENV['URSSAF_TEST_ENVIRONMENT'], $_SERVER['URSSAF_TEST_ENVIRONMENT']);
        }

        static::assertSame(Environment::Production, $configuration->environment());
    }

    public function testTheHttpTimeoutMayComeFromAnEnvironmentVariableAndTheStaleRuleIsThenSkipped(): void
    {
        $_ENV['URSSAF_TEST_TIMEOUT'] = '20';
        $_SERVER['URSSAF_TEST_TIMEOUT'] = '20';
        try {
            // A threshold shorter than any timeout: the build-time rule cannot compare it with a placeholder.
            $harness = Harness::boot(['http_timeout' => '%env(float:URSSAF_TEST_TIMEOUT)%', 'workflow' => ['stale_submission_after' => 'PT1S']]);
            $configuration = $harness->privateService(ServiceIds::API_CONFIGURATION, ApiConfiguration::class);
        } finally {
            unset($_ENV['URSSAF_TEST_TIMEOUT'], $_SERVER['URSSAF_TEST_TIMEOUT']);
        }

        static::assertSame(Environment::Sandbox, $configuration->environment());
    }
}
