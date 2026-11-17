<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Http;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Environment;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Http\ApiRequest;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Http\ApiResponse;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\HttpHeader;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\Stack;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\Stacks;

/**
 * One method per request shape.
 *
 * @mago-expect lint:too-many-methods
 */
final class JsonApiClientRequestTest extends TestCase
{
    private function send(Stack $stack, ApiRequest $request): ApiResponse
    {
        return $stack->jsonClient()->send(Stacks::tenant(), $request);
    }

    public function testItSendsAnAuthenticatedGetWithAnRfc3986Query(): void
    {
        $stack = Stacks::sandbox();
        $stack->respondToken();
        $stack->respond(200, '{"ok":"yes"}');

        $response = $this->send($stack, ApiRequest::get(Operation::VerifyEmployee, '/salaries', [
            'nom' => 'D ARTAGNAN',
            'prenom' => "L'\u{E9}",
            'plus' => 'a+b',
        ]));

        static::assertSame(200, $response->status());
        static::assertSame('yes', $response->payload()->string('ok'));
        $request = $stack->lastRequest();
        static::assertSame('GET', $request->getMethod());
        static::assertSame(
            'https://api-edi.urssaf.fr/tiersdecl/v1/paje/salaries?nom=D%20ARTAGNAN&prenom=L%27%C3%A9&plus=a%2Bb',
            (string) $request->getUri(),
        );
        static::assertSame('Bearer fictitious-access-token-0001', $request->getHeaderLine('Authorization'));
        static::assertSame('application/json', $request->getHeaderLine('Accept'));
        static::assertSame(ApiConfiguration::DEFAULT_USER_AGENT, $request->getHeaderLine('User-Agent'));
        static::assertFalse($request->hasHeader('Content-Type'));
        static::assertSame('', (string) $request->getBody());
    }

    public function testItEncodesNonAsciiQueryValuesAsUtf8Octets(): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->respond(200, '{}');

        $this->send($stack, ApiRequest::get(Operation::VerifyEmployee, '/salaries', ['prenom' => "\u{E9}"]));

        static::assertSame('prenom=%C3%A9', $stack->lastRequest()->getUri()->getQuery());
    }

    public function testItSendsThePostBodyVerbatimWithAContentType(): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->respond(200, '{}');

        $this->send($stack, ApiRequest::post(Operation::DeclareHomeCare, '/employeurs', [], ['k' => 1]));

        $request = $stack->lastRequest();
        static::assertSame('POST', $request->getMethod());
        static::assertSame('https://api-edi.urssaf.fr/tiersdecl/v1/paje/employeurs', (string) $request->getUri());
        static::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        static::assertSame('{"k":1}', (string) $request->getBody());
    }

    public function testAPostWithAQueryAndNoBodyCarriesNeitherBodyNorContentType(): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->respond(200, '');

        $this->send($stack, ApiRequest::post(Operation::RegisterMandate, '/mandats', ['numeroPaje' => '123']));

        $request = $stack->lastRequest();
        static::assertSame('POST', $request->getMethod());
        static::assertSame('https://api-edi.urssaf.fr/tiersdecl/v1/paje/mandats?numeroPaje=123', (string) $request->getUri());
        static::assertFalse($request->hasHeader('Content-Type'));
        static::assertSame('', (string) $request->getBody());
    }

    public function testADeleteCarriesItsQueryAndNoBody(): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->respond(200, '');

        $this->send($stack, ApiRequest::delete(Operation::CancelMandate, '/mandats', ['numeroPaje' => '123']));

        $request = $stack->lastRequest();
        static::assertSame('DELETE', $request->getMethod());
        static::assertSame('https://api-edi.urssaf.fr/tiersdecl/v1/paje/mandats?numeroPaje=123', (string) $request->getUri());
        static::assertFalse($request->hasHeader('Content-Type'));
        static::assertSame('', (string) $request->getBody());
    }

    public function testItSendsOnlyTheContractHeadersByDefault(): void
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->respond(200, '{}');

        $response = $this->send($stack, ApiRequest::get(Operation::VerifyEmployer, '/employeurs/1', []));

        static::assertSame(['Host', 'Authorization', 'Accept', 'User-Agent'], array_keys($stack->lastRequest()->getHeaders()));
        static::assertSame(200, $response->status());
        static::assertSame('request-id-1', $stack->ids->generate());
    }

    public function testItAddsAFreshRequestIdAndTheApplicationIdWhenConfigured(): void
    {
        $stack = Stacks::configured(new ApiConfiguration(
            Environment::Sandbox,
            null,
            'x-request-id',
            HttpHeader::of('recouv.application.id', 'app-42'),
        ));
        $stack->warmUpToken();
        $stack->respond(200, '{}');
        $stack->respond(200, '{}');

        $first = $this->send($stack, ApiRequest::get(Operation::VerifyEmployer, '/employeurs/1', []));
        $firstRequest = $stack->lastRequest();
        $second = $this->send($stack, ApiRequest::get(Operation::VerifyEmployer, '/employeurs/1', []));

        static::assertSame('request-id-1', $firstRequest->getHeaderLine('x-request-id'));
        static::assertSame('app-42', $firstRequest->getHeaderLine('recouv.application.id'));
        static::assertSame(200, $first->status());
        static::assertSame('request-id-2', $stack->lastRequest()->getHeaderLine('x-request-id'));
        static::assertSame(200, $second->status());
    }

    public function testItAddsTheApplicationIdAloneWhenNoRequestIdIsConfigured(): void
    {
        $stack = Stacks::configured(new ApiConfiguration(
            Environment::Sandbox,
            null,
            null,
            HttpHeader::of('recouv.application.id', 'app-42'),
        ));
        $stack->warmUpToken();
        $stack->respond(200, '{}');

        $this->send($stack, ApiRequest::get(Operation::VerifyEmployer, '/employeurs/1', []));

        $request = $stack->lastRequest();
        static::assertSame('app-42', $request->getHeaderLine('recouv.application.id'));
        static::assertFalse($request->hasHeader('x-request-id'));
    }

    public function testItUsesAConfiguredRequestIdHeaderName(): void
    {
        $stack = Stacks::configured(new ApiConfiguration(Environment::Sandbox, null, 'x-correlation'));
        $stack->warmUpToken();
        $stack->respond(200, '{}');

        $this->send($stack, ApiRequest::get(Operation::VerifyEmployer, '/employeurs/1', []));

        $request = $stack->lastRequest();
        static::assertSame('request-id-1', $request->getHeaderLine('x-correlation'));
        static::assertFalse($request->hasHeader('x-request-id'));
        static::assertFalse($request->hasHeader('recouv.application.id'));
    }

    public function testItUsesTheProductionHostTheUserAgentAndTheBaseUriOverride(): void
    {
        $production = Stacks::production();
        $production->warmUpToken();
        $production->respond(200, '{}');
        $this->send($production, ApiRequest::get(Operation::VerifyEmployer, '/employeurs/1', []));

        $custom = Stacks::configured(new ApiConfiguration(
            Environment::Sandbox,
            'https://proxy.example.test/base/',
            null,
            null,
            60,
            'custom-agent/1.0',
        ));
        $custom->warmUpToken();
        $custom->respond(200, '{}');
        $this->send($custom, ApiRequest::get(Operation::VerifyEmployer, '/employeurs/1', []));

        static::assertSame('https://api.urssaf.fr/tiersdecl/v1/paje/employeurs/1', (string) $production->lastRequest()->getUri());
        static::assertSame(
            'https://proxy.example.test/base/tiersdecl/v1/paje/employeurs/1',
            (string) $custom->lastRequest()->getUri(),
        );
        static::assertSame('custom-agent/1.0', $custom->lastRequest()->getHeaderLine('User-Agent'));
    }
}
