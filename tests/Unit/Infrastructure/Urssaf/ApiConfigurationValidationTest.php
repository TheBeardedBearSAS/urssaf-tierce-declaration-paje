<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-paje.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-paje contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf;

use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Environment;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\HttpHeader;

/**
 * One method per case.
 *
 * @mago-expect lint:too-many-methods
 */
final class ApiConfigurationValidationTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidBaseUriOverrides(): iterable
    {
        yield 'empty' => [''];
        yield 'no scheme' => ['api.urssaf.fr'];
        yield 'ftp' => ['ftp://h'];
        yield 'no host' => ['https://'];
        yield 'no host before path' => ['https:///base'];
        yield 'query' => ['https://h?x=1'];
        yield 'query after path' => ['https://h/p?x=1'];
        yield 'fragment' => ['https://h#f'];
        yield 'fragment after path' => ['https://h/p#f'];
        yield 'space' => ['https://h x'];
        yield 'space in path' => ['https://h/a b'];
        yield 'leading text' => ['x https://h'];
        yield 'trailing line feed' => ["https://h\n"];
    }

    #[DataProvider('provideInvalidBaseUriOverrides')]
    public function testItRefusesAnInvalidBaseUriOverride(string $override): void
    {
        $this->assertViolation(
            ApiConfiguration::BASE_URI_INVALID,
            'The base URI must be an absolute http(s) URI without query or fragment.',
            static fn(): ApiConfiguration => new ApiConfiguration(Environment::Sandbox, $override),
        );
    }

    public function testItRefusesANegativeSafetyMargin(): void
    {
        $this->assertViolation(
            'token_safety_margin.negative',
            'Invalid token_safety_margin: the value must not be negative.',
            static fn(): ApiConfiguration => new ApiConfiguration(Environment::Sandbox, null, null, null, -1),
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidUserAgents(): iterable
    {
        yield 'empty' => [''];
        yield 'line break' => ["a\r\nb"];
        yield 'leading space' => [' a'];
        yield 'trailing space' => ['a '];
        yield 'accent' => ['é'];
        yield 'trailing line feed' => ["a\n"];
    }

    #[DataProvider('provideInvalidUserAgents')]
    public function testItRefusesAnInvalidUserAgent(string $userAgent): void
    {
        $this->assertViolation(
            ApiConfiguration::USER_AGENT_INVALID,
            'The user agent must be visible ASCII without line breaks.',
            static fn(): ApiConfiguration => new ApiConfiguration(Environment::Sandbox, null, null, null, 60, $userAgent),
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidRequestIdHeaderNames(): iterable
    {
        yield 'not a token' => ['bad name'];
        yield 'reserved' => ['User-Agent'];
    }

    #[DataProvider('provideInvalidRequestIdHeaderNames')]
    public function testItRefusesAnInvalidRequestIdHeaderName(string $name): void
    {
        $this->expectException(InvalidValue::class);

        new ApiConfiguration(Environment::Sandbox, null, $name);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideDuplicateHeaderNames(): iterable
    {
        yield 'same case' => ['X-Id', 'X-Id'];
        yield 'other case' => ['X-Id', 'x-ID'];
    }

    #[DataProvider('provideDuplicateHeaderNames')]
    public function testItRefusesTheSameNameForBothHeaders(string $requestId, string $application): void
    {
        $this->assertViolation(
            ApiConfiguration::DUPLICATE_HEADER,
            'The request-id and application-id headers must have different names.',
            static fn(): ApiConfiguration => new ApiConfiguration(
                Environment::Sandbox,
                null,
                $requestId,
                HttpHeader::of($application, 'v'),
            ),
        );
    }

    /**
     * @return iterable<string, array{Operation}>
     */
    public static function provideOperationsThatCannotBeRetried(): iterable
    {
        yield '050' => [Operation::DeclareChildminder];
        yield '051' => [Operation::DeclareHomeCare];
        yield '020' => [Operation::VerifyEmployer];
        yield '046' => [Operation::PreDeclareHomeCare];
    }

    #[DataProvider('provideOperationsThatCannotBeRetried')]
    public function testItRefusesToDeclareAnythingButTheUnspecifiedWritesSafe(Operation $operation): void
    {
        $this->assertViolation(
            ApiConfiguration::RETRY_SAFE_WRITE_INVALID,
            'Only the writes of unspecified idempotency (023, 030, 031) can be declared safe to retry.',
            static fn(): ApiConfiguration => new ApiConfiguration(
                Environment::Sandbox,
                null,
                null,
                null,
                60,
                ApiConfiguration::DEFAULT_USER_AGENT,
                new DateTimeZone('UTC'),
                [Operation::RegisterMandate, $operation],
            ),
        );
    }

    public function testItAcceptsTheThreeUnspecifiedWrites(): void
    {
        $writes = [Operation::AssociateEmployee, Operation::RegisterMandate, Operation::CancelMandate];

        $configuration = new ApiConfiguration(
            Environment::Sandbox,
            null,
            null,
            null,
            60,
            ApiConfiguration::DEFAULT_USER_AGENT,
            new DateTimeZone('UTC'),
            $writes,
        );

        static::assertSame($writes, $configuration->retrySafeWrites());
    }

    /**
     * @param-immediately-invoked-callable $build
     *
     * @param callable(): mixed $build
     */
    private function assertViolation(string $code, string $message, callable $build): void
    {
        try {
            $build();
            static::fail('The configuration must be refused.');
        } catch (InvalidValue $exception) {
            static::assertSame($code, $exception->violationCode());
            static::assertSame($message, $exception->getMessage());
        }
    }
}
