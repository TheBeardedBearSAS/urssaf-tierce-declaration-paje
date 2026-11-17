<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Http;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Http\ApiResponse;

final class ApiResponseTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function provideBodies(): iterable
    {
        yield 'empty' => ['', true];
        yield 'blank' => ["  \n", true];
        yield 'empty object' => ['{}', false];
        yield 'object' => ['{"a":1}', false];
        yield 'garbage' => ['x', false];
    }

    #[DataProvider('provideBodies')]
    public function testIsEmptyOnlyForABlankBody(string $body, bool $expected): void
    {
        static::assertSame($expected, (new ApiResponse(200, $body))->isEmpty());
    }

    public function testItExposesStatusAndRawBody(): void
    {
        $response = new ApiResponse(201, '{"a":1}');

        static::assertSame(201, $response->status());
        static::assertSame('{"a":1}', $response->body());
    }

    public function testAnEmptyOrBlankBodyIsAnEmptyPayload(): void
    {
        static::assertTrue((new ApiResponse(200, '{}'))->payload()->isEmpty());
        static::assertTrue((new ApiResponse(200, ''))->payload()->isEmpty());
    }

    public function testPayloadIsDecodedFromTheBody(): void
    {
        $payload = (new ApiResponse(200, '{"a":"b"}'))->payload();

        static::assertFalse($payload->isEmpty());
        static::assertSame('b', $payload->string('a'));
    }

    public function testAnInvalidBodyThrowsOnPayloadOnly(): void
    {
        $response = new ApiResponse(200, 'not json');

        static::assertSame(200, $response->status());
        $this->expectException(UnexpectedUrssafResponse::class);
        $this->expectExceptionMessage('body is not valid JSON');
        $response->payload();
    }

    public function testDebugInfoHidesTheBody(): void
    {
        static::assertSame(
            ['status' => 200, 'body' => '***'],
            (new ApiResponse(200, '{"secret":"x"}'))->__debugInfo(),
        );
        static::assertSame(['status' => 200, 'body' => ''], (new ApiResponse(200, ''))->__debugInfo());
        static::assertStringNotContainsString('secret', print_r(new ApiResponse(200, '{"secret":"x"}'), true));
    }
}
