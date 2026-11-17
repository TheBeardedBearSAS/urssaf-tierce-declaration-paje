<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Http;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\OutcomeUnknown;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Environment;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Http\ApiRequest;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\JsonObject;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Fake\StaticCredentialsProvider;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Fake\TransportFailure;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\Stacks;
use Throwable;

final class JsonApiClientLoggingTest extends TestCase
{
    public function testItLogsTheCallWithoutARequestIdByDefault(): void
    {
        $stack = Stacks::stepping(250_000);
        $stack->respondToken();
        $stack->respond(200, '{"secret_looking":"fictitious name"}');

        $stack->jsonClient()->send(
            Stacks::tenant(),
            ApiRequest::get(Operation::VerifyEmployer, '/employeurs/1/verifier', ['nom' => 'FICTIF']),
        );

        $records = $stack->logger->records();
        static::assertCount(2, $records);
        static::assertSame('info', $records[1]['level']);
        static::assertSame('URSSAF call.', $records[1]['message']);
        static::assertSame(
            [
                'operation' => '020',
                'method' => 'GET',
                'path' => '/employeurs/{numeroPajeEmployeur}/verifier',
                'status' => 200,
                'duration_ms' => 250,
            ],
            $records[1]['context'],
        );
    }

    public function testItLogsTheRequestIdWhenConfigured(): void
    {
        $stack = Stacks::configured(new ApiConfiguration(Environment::Sandbox, null, 'x-request-id'));
        $stack->warmUpToken();
        $stack->respond(200, '{}');

        $stack->jsonClient()->send(
            Stacks::tenant(),
            ApiRequest::get(Operation::VerifyEmployer, '/employeurs/1/verifier', []),
        );

        static::assertSame(
            [
                'operation' => '020',
                'method' => 'GET',
                'path' => '/employeurs/{numeroPajeEmployeur}/verifier',
                'status' => 200,
                'duration_ms' => 0,
                'request_id' => 'request-id-1',
            ],
            $stack->logger->records()[0]['context'],
        );
    }

    public function testItLogsTheReplayAfterA401(): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->respond(401, '');
        $stack->respondToken();
        $stack->respond(200, '{}');

        $stack->jsonClient()->send(
            Stacks::tenant(),
            ApiRequest::get(Operation::VerifyEmployer, '/employeurs/1/verifier', ['nom' => 'FICTIF']),
        );

        $records = $stack->logger->records();
        static::assertSame('URSSAF call.', $records[0]['message']);
        static::assertSame(
            ['operation' => '020', 'method' => 'GET', 'path' => '/employeurs/{numeroPajeEmployeur}/verifier', 'status' => 401, 'duration_ms' => 0],
            $records[0]['context'],
        );
        static::assertSame('info', $records[1]['level']);
        static::assertSame('URSSAF call answered 401: token invalidated, replaying once.', $records[1]['message']);
        static::assertSame(['operation' => '020', 'path' => '/employeurs/{numeroPajeEmployeur}/verifier'], $records[1]['context']);
        static::assertSame('URSSAF token request.', $records[2]['message']);
        static::assertSame('URSSAF call.', $records[3]['message']);
        static::assertSame(
            ['operation' => '020', 'method' => 'GET', 'path' => '/employeurs/{numeroPajeEmployeur}/verifier', 'status' => 200, 'duration_ms' => 0],
            $records[3]['context'],
        );
        static::assertCount(4, $records);
    }

    public function testNeitherQueryBodyTokenNorSecretIsLogged(): void
    {
        $stack = Stacks::stepping(1000);
        $stack->respondToken();
        $stack->respond(200, '{"nomNaissance":"FICTIF"}');
        $stack->respondToken();
        $stack->respond(200, '{"nomNaissance":"FICTIF"}');
        $client = $stack->jsonClient();

        $client->send(Stacks::tenant(), ApiRequest::get(Operation::VerifyEmployee, '/salaries', [
            'nir' => '0000000000000',
            'nom' => 'FICTIF',
        ]));
        $client->send(Stacks::tenant(), ApiRequest::post(
            Operation::DeclareHomeCare,
            '/employeurs/123456789012/ged/declarer',
            [],
            ['iban' => 'FR00FICTITIOUS0IBAN000000'],
        ));

        $dump = $stack->logger->dump();
        foreach ([
            StaticCredentialsProvider::SHARED_VALUE,
            StaticCredentialsProvider::CLIENT_ID,
            'fictitious-access-token-0001',
            'Bearer',
            'FR00FICTITIOUS0IBAN000000',
            'FICTIF',
            '0000000000000',
            '123456789012',
            'nir',
            '?',
            'https://',
        ] as $forbidden) {
            static::assertStringNotContainsString($forbidden, $dump);
        }
    }

    public function testItLogsTransportFailuresAsWarningsWithoutTheMessage(): void
    {
        $stack = Stacks::configured(new ApiConfiguration(Environment::Sandbox, null, 'x-request-id'));
        $stack->warmUpToken();
        $stack->fail(new TransportFailure('timeout for https://h/salaries?nir=FICTIVE&nom=FICTIF'));

        try {
            $stack->jsonClient()->send(
                Stacks::tenant(),
                ApiRequest::get(Operation::VerifyEmployee, '/salaries', ['nir' => 'FICTIVE']),
            );
            static::fail('Expected an unavailable exception.');
        } catch (UrssafUnavailable $exception) {
            $records = $stack->logger->records();
            static::assertCount(1, $records);
            static::assertSame('warning', $records[0]['level']);
            static::assertSame('URSSAF call failed: transport error.', $records[0]['message']);
            static::assertSame(
                [
                    'operation' => '021',
                    'method' => 'GET',
                    'path' => '/salaries',
                    'duration_ms' => 0,
                    'exception' => TransportFailure::class,
                    'request_id' => 'request-id-1',
                ],
                $records[0]['context'],
            );
            static::assertStringNotContainsString('FICTIVE', $stack->logger->dump());
            static::assertNoMessageContains('FICTIVE', $exception);
        }
    }

    public function testTheTransportWarningOfAnUnsafeOperationHasNoRequestIdWhenNotConfigured(): void
    {
        $stack = Stacks::stepping(250_000);
        $stack->warmUpToken();
        $stack->fail(new TransportFailure('timeout for https://h/employeurs/123456789012/ged/declarer'));

        try {
            $stack->jsonClient()->send(
                Stacks::tenant(),
                ApiRequest::post(Operation::DeclareHomeCare, '/employeurs/123456789012/ged/declarer', [], JsonObject::of([])),
            );
            static::fail('Expected an unknown outcome.');
        } catch (OutcomeUnknown $exception) {
            static::assertSame(
                [
                    'operation' => '051',
                    'method' => 'POST',
                    'path' => '/employeurs/{numeroPajeEmployeur}/ged/declarer',
                    'duration_ms' => 250,
                    'exception' => TransportFailure::class,
                ],
                $stack->logger->records()[0]['context'],
            );
            static::assertNoMessageContains('https://h', $exception);
        }
    }

    private static function assertNoMessageContains(string $needle, Throwable $exception): void
    {
        for ($current = $exception; $current instanceof Throwable; $current = $current->getPrevious()) {
            static::assertStringNotContainsString($needle, $current->getMessage());
        }
    }
}
