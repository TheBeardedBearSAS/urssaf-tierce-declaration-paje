<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\DependencyInjection;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\DependencyInjection\ConfigurationRules;

/**
 * The predicates of the configuration tree, tested without a container (each one is true when the value is invalid).
 */
final class ConfigurationRulesTest extends TestCase
{
    #[DataProvider('staleThresholds')]
    public function testTheStaleThresholdMustExceedTheHttpTimeout(bool $invalid, string $stale, int|float|string|null $timeout): void
    {
        static::assertSame($invalid, ConfigurationRules::staleSubmissionNotAboveHttpTimeout(['http_timeout' => $timeout, 'workflow' => ['stale_submission_after' => $stale]]));
    }

    /**
     * @return iterable<string, array{bool, string, int|float|string|null}>
     */
    public static function staleThresholds(): iterable
    {
        yield 'longer' => [false, 'PT15M', 15.0];
        yield 'just longer' => [false, 'PT16S', 15.0];
        yield 'equal' => [true, 'PT15S', 15.0];
        yield 'shorter' => [true, 'PT5S', 15.0];
        yield 'integer timeout' => [true, 'PT1M', 60];
        yield 'a month is counted in days' => [false, 'P1M', 3600.0];
        yield 'a day is counted in hours' => [true, 'P1D', 90_000.0];
        yield 'a placeholder timeout cannot be compared' => [false, 'PT1S', '%env(float:URSSAF_TIMEOUT)%'];
        yield 'a null timeout cannot be compared' => [false, 'PT1S', null];
    }

    public function testTheHeaderNamesAreComparedWithoutRegardToTheCase(): void
    {
        static::assertTrue(ConfigurationRules::duplicateHeaderNames(['request_id_header' => 'x-id', 'application_id_header' => ['name' => 'X-Id']]));
        static::assertTrue(ConfigurationRules::duplicateHeaderNames(['request_id_header' => 'X-Id', 'application_id_header' => ['name' => 'x-id']]));
        static::assertFalse(ConfigurationRules::duplicateHeaderNames(['request_id_header' => 'X-Id', 'application_id_header' => ['name' => 'X-Application']]));
        static::assertFalse(ConfigurationRules::duplicateHeaderNames(['request_id_header' => null, 'application_id_header' => ['name' => 'X-Id']]));
        static::assertFalse(ConfigurationRules::duplicateHeaderNames(['request_id_header' => 'X-Id', 'application_id_header' => ['name' => null]]));
    }

    public function testTheDefaultPageSizeMayEqualTheMaximum(): void
    {
        static::assertFalse(ConfigurationRules::defaultPageAboveMax(['default_page_size' => 50, 'max_page_size' => 50]));
        static::assertFalse(ConfigurationRules::defaultPageAboveMax(['default_page_size' => 10, 'max_page_size' => 50]));
        static::assertTrue(ConfigurationRules::defaultPageAboveMax(['default_page_size' => 51, 'max_page_size' => 50]));
    }

    public function testTheCustomDriverNeedsEachOfTheThreeRepositories(): void
    {
        $complete = ['driver' => 'custom', 'employer_account_repository' => 'a', 'mandate_repository' => 'm', 'declaration_record_repository' => 'd'];

        static::assertFalse(ConfigurationRules::customDriverWithoutRepositories($complete));
        static::assertFalse(ConfigurationRules::customDriverWithoutRepositories([...$complete, 'driver' => 'in_memory', 'mandate_repository' => null]));
        foreach (['employer_account_repository', 'mandate_repository', 'declaration_record_repository'] as $missing) {
            static::assertTrue(ConfigurationRules::customDriverWithoutRepositories([...$complete, $missing => null]), $missing);
        }
    }

    public function testTheApplicationHeaderNeedsBothItsNameAndItsValueOrNeither(): void
    {
        static::assertFalse(ConfigurationRules::incompleteApplicationIdHeader(['name' => null, 'value' => null]));
        static::assertFalse(ConfigurationRules::incompleteApplicationIdHeader(['name' => '', 'value' => '']));
        static::assertFalse(ConfigurationRules::incompleteApplicationIdHeader(['name' => 'X-Application', 'value' => 'my-application']));
        static::assertTrue(ConfigurationRules::incompleteApplicationIdHeader(['name' => 'X-Application', 'value' => null]));
        static::assertTrue(ConfigurationRules::incompleteApplicationIdHeader(['name' => null, 'value' => 'my-application']));
        static::assertTrue(ConfigurationRules::incompleteApplicationIdHeader(['name' => '', 'value' => 'my-application']));
    }

    public function testTheCredentialSourcesAreMutuallyExclusiveAndRequired(): void
    {
        static::assertTrue(ConfigurationRules::hasBothCredentialSources(['credentials' => ['t' => []], 'credentials_provider' => 'p']));
        static::assertFalse(ConfigurationRules::hasBothCredentialSources(['credentials' => [], 'credentials_provider' => 'p']));
        static::assertFalse(ConfigurationRules::hasBothCredentialSources(['credentials' => ['t' => []], 'credentials_provider' => null]));
        static::assertTrue(ConfigurationRules::hasNoCredentialSource(['credentials' => [], 'credentials_provider' => null]));
        static::assertFalse(ConfigurationRules::hasNoCredentialSource(['credentials' => ['t' => []], 'credentials_provider' => null]));
        static::assertFalse(ConfigurationRules::hasNoCredentialSource(['credentials' => [], 'credentials_provider' => 'p']));
    }

    public function testTheOutboxNeedsAServiceWithTheCustomDriver(): void
    {
        $outbox = ['enabled' => true, 'service' => null];

        static::assertTrue(ConfigurationRules::outboxWithoutStorage(['outbox' => $outbox, 'persistence' => ['driver' => 'custom']]));
        static::assertFalse(ConfigurationRules::outboxWithoutStorage(['outbox' => $outbox, 'persistence' => ['driver' => 'in_memory']]));
        static::assertFalse(ConfigurationRules::outboxWithoutStorage(['outbox' => [...$outbox, 'service' => 'mine'], 'persistence' => ['driver' => 'custom']]));
        static::assertFalse(ConfigurationRules::outboxWithoutStorage(['outbox' => ['enabled' => false, 'service' => null], 'persistence' => ['driver' => 'custom']]));
    }
}
