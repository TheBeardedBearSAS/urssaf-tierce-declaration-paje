<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Support;

use Http\Message\RequestMatcher\RequestMatcher;
use Http\Mock\Client;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\PajeEmployerNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\UrssafEndpoints;
use TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Wire\ApiPaths;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\Stack;

/**
 * Scripted URSSAF PAJE platform behind the mocked PSR-18 client. Every route answers a success by default (the wire
 * fixtures of tests/Fixtures/wire) and can be switched to a given status or to a transport failure. Requests are
 * counted per route.
 *
 * One constant and one route per operation of the contract.
 */
final class HttpScript
{
    public const ROUTE_AUTHENTICATION = 'authentication';
    public const ROUTE_020 = '020';
    public const ROUTE_021 = '021';
    public const ROUTE_022 = '022';
    public const ROUTE_023 = '023';
    public const ROUTE_030 = '030';
    public const ROUTE_031 = '031';
    public const ROUTE_040 = '040';
    public const ROUTE_041 = '041';
    public const ROUTE_045 = '045';
    public const ROUTE_046 = '046';
    public const ROUTE_050 = '050';
    public const ROUTE_051 = '051';

    private const SAMPLE_EMPLOYER = 'Y1869005780009';

    /** @var array<string, RouteEndpoint> */
    private array $routes;

    public function __construct(Client $client)
    {
        $this->routes = [
            self::ROUTE_AUTHENTICATION => new RouteEndpoint(200, Stack::TOKEN_RESPONSE),
            self::ROUTE_020 => new RouteEndpoint(200, self::fixture('verify_employer_response.json')),
            self::ROUTE_021 => new RouteEndpoint(200, self::fixture('verify_employee_response.json')),
            self::ROUTE_022 => new RouteEndpoint(200, self::fixture('verify_children_response.json')),
            self::ROUTE_023 => new RouteEndpoint(200, self::fixture('associate_employee_response.json')),
            self::ROUTE_030 => new RouteEndpoint(200, ''),
            self::ROUTE_031 => new RouteEndpoint(200, ''),
            self::ROUTE_040 => new RouteEndpoint(200, self::fixture('estimate_response.json')),
            self::ROUTE_041 => new RouteEndpoint(200, self::fixture('estimate_response.json')),
            self::ROUTE_045 => new RouteEndpoint(200, self::fixture('predeclare_response_valid.json')),
            self::ROUTE_046 => new RouteEndpoint(200, self::fixture('predeclare_response_valid.json')),
            self::ROUTE_050 => new RouteEndpoint(200, self::fixture('declare_response_valid.json')),
            self::ROUTE_051 => new RouteEndpoint(200, self::fixture('declare_response_valid.json')),
        ];
        $employer = PajeEmployerNumber::of(self::SAMPLE_EMPLOYER);
        $matchers = [
            self::ROUTE_AUTHENTICATION => new RequestMatcher(self::path(UrssafEndpoints::TOKEN_PATH, ''), null, ['POST']),
            self::ROUTE_020 => new RequestMatcher(self::path(ApiPaths::verifyEmployer($employer)), null, ['GET']),
            self::ROUTE_021 => new RequestMatcher(self::path(ApiPaths::verifyEmployee()), null, ['POST']),
            self::ROUTE_022 => new RequestMatcher(self::path(ApiPaths::verifyChildren($employer)), null, ['POST']),
            self::ROUTE_023 => new RequestMatcher(self::path(ApiPaths::associateEmployee($employer)), null, ['POST']),
            self::ROUTE_030 => new RequestMatcher(self::path(ApiPaths::registerMandate()), null, ['POST']),
            self::ROUTE_031 => new RequestMatcher(self::path(ApiPaths::cancelMandate()), null, ['DELETE']),
            self::ROUTE_040 => new RequestMatcher(self::path(ApiPaths::estimateChildminder()), null, ['POST']),
            self::ROUTE_041 => new RequestMatcher(self::path(ApiPaths::estimateHomeCare()), null, ['POST']),
            self::ROUTE_045 => new RequestMatcher(self::path(ApiPaths::preDeclareChildminder($employer)), null, ['POST']),
            self::ROUTE_046 => new RequestMatcher(self::path(ApiPaths::preDeclareHomeCare($employer)), null, ['POST']),
            self::ROUTE_050 => new RequestMatcher(self::path(ApiPaths::declareChildminder($employer)), null, ['POST']),
            self::ROUTE_051 => new RequestMatcher(self::path(ApiPaths::declareHomeCare($employer)), null, ['POST']),
        ];
        foreach ($matchers as $route => $matcher) {
            $client->on($matcher, $this->routes[$route]);
        }
    }

    /**
     * The content of a wire fixture of tests/Fixtures/wire.
     */
    public static function fixture(string $name): string
    {
        $content = file_get_contents(__DIR__ . '/../../../Fixtures/wire/' . $name);
        \assert(\is_string($content), 'Unreadable wire fixture ' . $name);

        return $content;
    }

    /**
     * The request path regular expression of a path of the API: the sample employer number matches any employer.
     */
    private static function path(string $path, string $base = UrssafEndpoints::API_BASE_PATH): string
    {
        $pattern = preg_quote($base . $path, '/');

        return str_replace(preg_quote(self::SAMPLE_EMPLOYER, '/'), '[^/]+', $pattern) . '$';
    }

    public function calls(string $route): int
    {
        return $this->routes[$route]->calls();
    }

    /**
     * @param array<string, string> $headers
     */
    public function respond(string $route, int $status, string $body, array $headers = []): void
    {
        $this->routes[$route]->respond($status, $body, $headers);
    }

    public function respondWithFixture(string $route, int $status, string $fixture): void
    {
        $this->routes[$route]->respond($status, self::fixture($fixture));
    }

    public function failTransport(string $route): void
    {
        $this->routes[$route]->failTransport();
    }
}
