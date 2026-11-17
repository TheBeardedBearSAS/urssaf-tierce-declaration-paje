<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Contract;

use DateTimeImmutable;
use DateTimeZone;
use Nyholm\Psr7\Request;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\ApiError;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\RejectionKind;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafRequestRejected;

/**
 * Offline checks of the contract suite's own support code (it never reaches the network and needs no credentials):
 * the variables, the gateway wiring, the redaction of the exchange capture and of the report, the data builders.
 *
 * @mago-expect lint:too-many-methods Each support class and each behaviour is one test method.
 */
#[Group('contract-support')]
final class SandboxSupportTest extends TestCase
{
    /** Fictitious personal data that must never reach an exchange capture or the report. */
    private const SECRET_NUMBER = 'Y1869005780009';
    private const SECRET_NIR = '275037512345678';
    private const SECRET_DATE = '1975-02-23';
    private const SECRET_NAME = 'DUPONTSECRET';
    private const SECRET_REFERENCE = 'REF-SECRET-1';
    private const AUTH_MARKER = 'token-never-kept';

    private string $reportPath;

    /** @var array{points: null|array<string, array{point: string, state: string, value: mixed, test: string}>, path: null|string, tokenHost: string, baseUriHost: string, writesAllowed: bool} */
    private array $saved;

    protected function setUp(): void
    {
        $this->reportPath = \dirname(__DIR__, 2) . '/var/contract/support-report.json';
        $this->saved = SandboxReport::reset();
        SandboxReport::usePath($this->reportPath);
    }

    protected function tearDown(): void
    {
        SandboxReport::restore($this->saved);
        if (is_file($this->reportPath)) {
            unlink($this->reportPath);
        }
    }

    public function testEnvironmentWithoutVariablesHasNoCredentialsAndNothingConfigured(): void
    {
        $env = SandboxEnvironment::fromArray([]);

        static::assertFalse($env->hasCredentials());
        static::assertNull($env->baseUri());
        static::assertNull($env->tokenUri());
        static::assertNull($env->employer());
        static::assertNull($env->employeeNumber());
        static::assertNull($env->child());
        static::assertFalse($env->writesAllowed());
    }

    public function testEnvironmentReadsValidValues(): void
    {
        $env = SandboxEnvironment::fromArray([
            SandboxEnvironment::CLIENT_ID => 'id',
            SandboxEnvironment::CLIENT_SECRET => 'secret',
            SandboxEnvironment::BASE_URI => 'https://sandbox.example.test',
            SandboxEnvironment::TOKEN_URI => 'https://token.example.test/token',
            SandboxEnvironment::EMPLOYER_NUMBER => self::SECRET_NUMBER,
            SandboxEnvironment::EMPLOYER_BIRTH_DATE => SandboxData::FICTITIOUS_BIRTH_DATE,
            SandboxEnvironment::EMPLOYEE_NUMBER => 'Y2926822480004',
            SandboxEnvironment::CHILD_LAST_NAME => 'L\'HOMME',
            SandboxEnvironment::CHILD_FIRST_NAME => 'JEAN-MICHEL',
            SandboxEnvironment::CHILD_BIRTH_DATE => '2015-01-12',
            SandboxEnvironment::ALLOW_WRITES => '1',
        ]);

        static::assertTrue($env->hasCredentials());
        static::assertSame('id', $env->credentials()->clientId());
        static::assertSame('https://sandbox.example.test', $env->baseUri());
        static::assertSame('https://token.example.test/token', $env->tokenUri());
        static::assertSame(self::SECRET_NUMBER, $env->employer()?->number()->value());
        static::assertSame('Y2926822480004', $env->employeeNumber()?->value());
        static::assertSame('JEAN-MICHEL', $env->child()?->firstName()->value());
        static::assertTrue($env->writesAllowed());
        static::assertFalse(SandboxEnvironment::fromArray([SandboxEnvironment::ALLOW_WRITES => 'true'])->writesAllowed());
    }

    public function testEnvironmentRefusesTheProductionHost(): void
    {
        $env = SandboxEnvironment::fromArray([SandboxEnvironment::BASE_URI => 'https://API.urssaf.fr']);

        $this->expectException(SandboxMisconfigured::class);
        $this->expectExceptionMessage(SandboxEnvironment::BASE_URI);
        $env->baseUri();
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideRefusedUris(): iterable
    {
        yield 'production host' => ['https://api.urssaf.fr/api/oauth/v1/token', 'targets the production host'];
        yield 'production host, upper case, trailing dot' => ['https://API.URSSAF.FR./token', 'targets the production host'];
        yield 'http scheme' => ['http://sandbox.example.test/token', 'must be an https URI'];
        yield 'no host' => ['https:token', 'has no host'];
    }

    #[DataProvider('provideRefusedUris')]
    public function testEnvironmentRefusesAnUnsafeTokenUri(string $uri, string $reason): void
    {
        $env = SandboxEnvironment::fromArray([SandboxEnvironment::TOKEN_URI => $uri]);

        $this->expectException(SandboxMisconfigured::class);
        $this->expectExceptionMessage(SandboxEnvironment::TOKEN_URI);
        $this->expectExceptionMessage($reason);
        $env->tokenUri();
    }

    public function testEnvironmentRefusesTheProductionHostWithATrailingDotAsBaseUri(): void
    {
        $env = SandboxEnvironment::fromArray([SandboxEnvironment::BASE_URI => 'https://API.urssaf.fr.']);

        $this->expectException(SandboxMisconfigured::class);
        $this->expectExceptionMessage('targets the production host');
        $env->baseUri();
    }

    public function testEnvironmentAcceptsAnHttpsTokenUri(): void
    {
        static::assertSame(
            'https://sandbox.example.test/token',
            SandboxEnvironment::fromArray([SandboxEnvironment::TOKEN_URI => 'https://sandbox.example.test/token'])->tokenUri(),
        );
    }

    public function testEnvironmentNamesTheVariableNeverTheValue(): void
    {
        $bad = SandboxEnvironment::fromArray([SandboxEnvironment::EMPLOYEE_NUMBER => 'secret-value']);
        try {
            $bad->employeeNumber();
            static::fail('A wrong employee number must be refused.');
        } catch (SandboxMisconfigured $e) {
            static::assertStringContainsString(SandboxEnvironment::EMPLOYEE_NUMBER, $e->getMessage());
            static::assertStringNotContainsString('secret-value', $e->getMessage());
        }

        $badEmployer = SandboxEnvironment::fromArray([
            SandboxEnvironment::EMPLOYER_NUMBER => 'secret-value',
            SandboxEnvironment::EMPLOYER_BIRTH_DATE => SandboxData::FICTITIOUS_BIRTH_DATE,
        ]);
        try {
            $badEmployer->employer();
            static::fail('A wrong employer number must be refused.');
        } catch (SandboxMisconfigured $e) {
            static::assertStringContainsString(SandboxEnvironment::EMPLOYER_NUMBER, $e->getMessage());
            static::assertStringNotContainsString('secret-value', $e->getMessage());
        }

        $noDate = SandboxEnvironment::fromArray([SandboxEnvironment::EMPLOYER_NUMBER => self::SECRET_NUMBER]);
        $this->expectException(SandboxMisconfigured::class);
        $this->expectExceptionMessage(SandboxEnvironment::EMPLOYER_BIRTH_DATE);
        $noDate->employer();
    }

    public function testEnvironmentRequiresTheEmployerNumberWithItsBirthDate(): void
    {
        $env = SandboxEnvironment::fromArray([SandboxEnvironment::EMPLOYER_BIRTH_DATE => SandboxData::FICTITIOUS_BIRTH_DATE]);

        $this->expectException(SandboxMisconfigured::class);
        $this->expectExceptionMessage(SandboxEnvironment::EMPLOYER_NUMBER);
        $env->employer();
    }

    public function testEnvironmentRefusesAnInvalidBirthDateWithoutEchoingIt(): void
    {
        $env = SandboxEnvironment::fromArray([
            SandboxEnvironment::EMPLOYER_NUMBER => self::SECRET_NUMBER,
            SandboxEnvironment::EMPLOYER_BIRTH_DATE => '29/03/1980',
        ]);

        try {
            $env->employer();
            static::fail('A wrong date must be refused.');
        } catch (SandboxMisconfigured $e) {
            static::assertSame('URSSAF_SANDBOX_EMPLOYER_BIRTH_DATE is not a YYYY-MM-DD date.', $e->getMessage());
        }
    }

    public function testChildVariablesAreAllOrNothing(): void
    {
        $partial = SandboxEnvironment::fromArray([SandboxEnvironment::CHILD_LAST_NAME => 'DUPONT']);
        try {
            $partial->child();
            static::fail('A partial child must be refused.');
        } catch (SandboxMisconfigured $e) {
            static::assertStringContainsString(SandboxEnvironment::CHILD_FIRST_NAME, $e->getMessage());
            static::assertStringNotContainsString('DUPONT', $e->getMessage());
        }

        $accented = SandboxEnvironment::fromArray([
            SandboxEnvironment::CHILD_LAST_NAME => 'DUPONTÉ',
            SandboxEnvironment::CHILD_FIRST_NAME => 'JEAN',
            SandboxEnvironment::CHILD_BIRTH_DATE => '2015-01-12',
        ]);
        try {
            $accented->child();
            static::fail('An accented name must be refused.');
        } catch (SandboxMisconfigured $e) {
            static::assertStringNotContainsString('DUPONT', $e->getMessage());
        }

        $badDate = SandboxEnvironment::fromArray([
            SandboxEnvironment::CHILD_LAST_NAME => 'DUPONT',
            SandboxEnvironment::CHILD_FIRST_NAME => 'JEAN',
            SandboxEnvironment::CHILD_BIRTH_DATE => 'not-a-date',
        ]);
        $this->expectException(SandboxMisconfigured::class);
        $this->expectExceptionMessage(SandboxEnvironment::CHILD_BIRTH_DATE);
        $badDate->child();
    }

    public function testProcessEnvironmentIgnoresBlankValues(): void
    {
        static::assertNull(SandboxEnvironment::env('URSSAF_SANDBOX_DEFINITELY_UNSET'));
        putenv('URSSAF_SANDBOX_SUPPORT_TEST=  value ');
        static::assertSame('value', SandboxEnvironment::env('URSSAF_SANDBOX_SUPPORT_TEST'));
        putenv('URSSAF_SANDBOX_SUPPORT_TEST=   ');
        static::assertNull(SandboxEnvironment::env('URSSAF_SANDBOX_SUPPORT_TEST'));
        putenv('URSSAF_SANDBOX_SUPPORT_TEST');
    }

    public function testGatewayIsNullWithoutCredentialsEvenWithAProductionBaseUri(): void
    {
        $env = SandboxEnvironment::fromArray([SandboxEnvironment::BASE_URI => 'https://api.urssaf.fr']);

        static::assertNull(SandboxGateway::fromEnvironment($env));
    }

    public function testGatewayRefusesAProductionTokenUriEagerly(): void
    {
        $env = SandboxEnvironment::fromArray([
            SandboxEnvironment::CLIENT_ID => 'id',
            SandboxEnvironment::CLIENT_SECRET => 'secret',
            SandboxEnvironment::TOKEN_URI => 'https://api.urssaf.fr/token',
        ]);

        $this->expectException(SandboxMisconfigured::class);
        $this->expectExceptionMessage(SandboxEnvironment::TOKEN_URI);
        SandboxGateway::fromEnvironment($env);
    }

    public function testGatewayIsWiredWithoutAnyNetworkCall(): void
    {
        $env = SandboxEnvironment::fromArray([
            SandboxEnvironment::CLIENT_ID => 'id',
            SandboxEnvironment::CLIENT_SECRET => 'secret',
            SandboxEnvironment::TOKEN_URI => 'https://token.example.test/token',
            SandboxEnvironment::ALLOW_WRITES => '1',
        ]);

        $sandbox = SandboxGateway::fromEnvironment($env);

        static::assertInstanceOf(SandboxGateway::class, $sandbox);
        static::assertSame('https://token.example.test/token', $sandbox->tokenUriOverride);
        static::assertSame('https://api-edi.urssaf.fr/api/oauth/v1/token', $sandbox->configuration->tokenUri());
        static::assertSame([], $sandbox->configuration->retrySafeWrites(), 'No write is declared safe to retry.');
        static::assertSame([], $sandbox->http->exchanges());
        SandboxReport::flush();
        $report = $this->readReport();
        static::assertSame('api-edi.urssaf.fr', $report['baseUriHost']);
        static::assertSame('token.example.test', $report['tokenHost']);
        static::assertTrue($report['writesAllowed']);
    }

    public function testExchangeKeepsTheShapeOnly(): void
    {
        $exchange = Exchange::from($this->employerRequest(), $this->richResponse());

        static::assertSame('GET', $exchange->method);
        static::assertSame('host.example.test', $exchange->host);
        static::assertSame('/tiersdecl/v1/paje/employeurs/{numero}/verifier', $exchange->pathTemplate);
        static::assertSame(200, $exchange->status);
        static::assertTrue($exchange->isSuccessful());
        static::assertSame(['content-type', 'retry-after', 'x-request-id'], $exchange->headerNames);
        static::assertSame('30', $exchange->retryAfter);
        static::assertSame(Exchange::OBJECT, $exchange->bodyShape);
        static::assertContains('declaration', $exchange->bodyKeys);
        static::assertSame(['etatCompteEnLigne' => 'Coordonnées bancaires invalides'], $exchange->enumValues);
        static::assertSame(
            ['employeurAutoriseOk' => 'bool', 'numeroPajeSalarie' => 'string', 'referenceDocumentaire' => 'string'],
            $exchange->jsonTypes,
        );
        static::assertSame([], $exchange->codes);
        static::assertSame(['ER_API_023_NB_0000', 'ER_API_DECLA_NB_0010'], $exchange->nonBlockingCodes);
        static::assertSame(['ER_API_023_NB_0000', 'ER_API_DECLA_NB_0010'], $exchange->allCodes());
        static::assertSame(3, $exchange->amountMaxDecimals);
        static::assertSame(2, $exchange->rateMaxDecimals);
    }

    public function testExchangeAndReportNeverHoldPersonalData(): void
    {
        $request = $this->employerRequest();
        $response = $this->richResponse();

        $exchange = Exchange::from($request, $response);
        $client = new SandboxHttpClient($this->staticClient($response), null);
        $client->sendRequest($request);
        SandboxReport::merge('predeclaration', '045', ['nonBlocking' => $exchange->nonBlockingCodes], 'test');
        SandboxReport::flush();

        $file = file_get_contents($this->reportPath);
        static::assertIsString($file);
        $captured = json_encode($exchange, \JSON_THROW_ON_ERROR) . $file;
        foreach ([self::SECRET_NUMBER, self::SECRET_NIR, self::SECRET_DATE, self::SECRET_NAME, self::SECRET_REFERENCE, self::AUTH_MARKER, 'never kept', 'dateNaissance', 'nirSalarie'] as $secret) {
            static::assertStringNotContainsString($secret, $captured);
        }
        static::assertSame('{"employeurAutoriseOk":true', substr((string) $response->getBody(), 0, 27), 'The body is rewound for the gateway.');
    }

    public function testExchangeBodyShapes(): void
    {
        $request = new Request('POST', 'https://host.example.test/x');
        $shape = static fn(string $body, array $headers = []): Exchange => Exchange::from($request, new Response(400, $headers, $body));

        static::assertSame(Exchange::EMPTY, $shape('')->bodyShape);
        static::assertSame(Exchange::NOT_JSON, $shape('<html>')->bodyShape);
        static::assertSame(Exchange::SCALAR, $shape('"text"')->bodyShape);
        $list = $shape('[{"code":"PARAM_INVALIDE","message":"never kept"},{"code":"ER_API_DECLA_00071"},"x"]');
        static::assertSame(Exchange::LIST, $list->bodyShape);
        static::assertSame(['code', 'message'], $list->bodyKeys);
        static::assertSame(['PARAM_INVALIDE', 'ER_API_DECLA_00071'], $list->codes);
        static::assertSame([], $shape('[1,2]')->bodyKeys);
        static::assertSame(['MANDAT_INEXISTANT'], $shape('{"code":"MANDAT_INEXISTANT "}')->codes);
        static::assertSame(['other(length=8)'], $shape('{"code":"not a code"}')->codes);
        static::assertSame('other(length=8)', $shape('{"code":"not a code"}')->enumValues['code']);
        static::assertSame('ER_API_DECLA_0001', $shape('{"code":"ER_API_DECLA_0001"}')->enumValues['code']);
        static::assertSame([], $shape('{"code":12,"etatCompteEnLigne":3}')->enumValues);
        static::assertSame('Bearer', $shape('{"token_type":"Bearer"}')->enumValues['token_type']);
        static::assertSame('other(length=3)', $shape('{"token_type":"Foo"}')->enumValues['token_type']);
        static::assertSame('other(length=12)', $shape('{"etatCompteEnLigne":"DUPONT 12345"}')->enumValues['etatCompteEnLigne']);
        static::assertSame('Actif', $shape('{"etatCompteEnLigne":"Actif"}')->enumValues['etatCompteEnLigne']);
        static::assertSame('unparseable', $shape('{}', ['Retry-After' => 'soon'])->retryAfter);
        static::assertSame('Wed, 21 Oct 2026 07:28:00 GMT', $shape('{}', ['Retry-After' => 'Wed, 21 Oct 2026 07:28:00 GMT'])->retryAfter);
        static::assertNull($shape('{}')->retryAfter);
        static::assertNull($shape('{"a":1.5}')->amountMaxDecimals);
        static::assertSame(0, $shape('{"mntA":12}')->amountMaxDecimals);
        static::assertSame(2, $shape('{"mntA":1.5,"b":{"mntSalaire":2.25}}')->amountMaxDecimals);
        static::assertNull($shape('{"mntA":1.5}')->rateMaxDecimals);
        static::assertSame(0, $shape('{"txPas":7}')->rateMaxDecimals);
        static::assertEquals(
            ['expires_in' => 'float', 'numeroPajeSalarie' => 'integer', 'indcAma' => 'null', 'referenceDocumentaire' => 'array', 'dateVirement' => 'object'],
            $shape('{"expires_in":3600.5,"numeroPajeSalarie":5,"indcAma":null,"referenceDocumentaire":[],"dateVirement":{"a":1}}')->jsonTypes,
        );
        static::assertSame([], $shape('{"erreursNonBloquantes":[{"message":"no code"},1]}')->nonBlockingCodes);
    }

    public function testHttpClientRewritesOnlyTheTokenRequestAndRecordsShapes(): void
    {
        $inner = new class implements ClientInterface {
            /** @var list<RequestInterface> */
            private array $requests = [];

            public function hostAt(int $index): string
            {
                return $this->requests[$index]->getUri()->getHost();
            }

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $this->requests[] = $request;

                return match (true) {
                    str_contains($request->getUri()->getPath(), 'oauth') => new Response(200, [], '{"access_token":"fake","expires_in":3600}'),
                    str_contains($request->getUri()->getPath(), 'estimer') => new Response(200, [], '{"mntCotiEmplAcharge":1.5,"declaration":{"txPas":7.25}}'),
                    str_contains($request->getUri()->getPath(), 'declarer') => new Response(400, [], '[{"code":"ER_API_DECLA_00020"},{"code":"MANDAT_INEXISTANT"},{"code":"ER_API_DECLA_0042"}]'),
                    default => new Response(429, ['Retry-After' => '12'], '{"code":"QUOTA_ERROR"}'),
                };
            }
        };
        $client = new SandboxHttpClient($inner, 'https://token.example.test/oauth/token');

        $client->sendRequest(new Request('POST', 'https://api-edi.urssaf.fr/api/oauth/v1/token', [], 'client_secret=never-recorded'));
        $mark = $client->mark();
        $client->sendRequest(new Request('GET', 'https://api-edi.urssaf.fr/tiersdecl/v1/paje/anything'));
        $client->sendRequest(new Request('POST', 'https://api-edi.urssaf.fr/tiersdecl/v1/paje/ama/estimer'));
        $client->sendRequest(new Request('POST', 'https://api-edi.urssaf.fr/tiersdecl/v1/paje/employeurs/' . self::SECRET_NUMBER . '/ged/declarer?dtNaissanceEmployeur=' . self::SECRET_DATE));
        $probe = $client->probe(new Request('POST', 'https://other.example.test/api/oauth/v1/token'));

        static::assertSame('token.example.test', $inner->hostAt(0));
        static::assertSame('api-edi.urssaf.fr', $inner->hostAt(1));
        static::assertSame('other.example.test', $inner->hostAt(4));
        static::assertCount(5, $client->exchanges());
        static::assertCount(4, $client->since($mark));
        static::assertSame($probe, $client->last());
        static::assertSame(200, $probe->status);
        static::assertTrue($client->isTokenExchange($probe));
        static::assertFalse($client->isTokenExchange($client->exchanges()[1]));

        $points = SandboxReport::points();
        static::assertEquals(
            ['retryAfter' => '12', 'bodyShape' => 'object', 'bodyKeys' => ['code'], 'codes' => ['QUOTA_ERROR']],
            $points['quota-429']['value'],
        );
        static::assertEquals(['headerNames' => ['retry-after'], 'retryAfterSeen' => true], $points['headers']['value']);
        static::assertEquals(
            [
                'seen' => ['ER_API_DECLA_00020', 'ER_API_DECLA_0042', 'MANDAT_INEXISTANT', 'QUOTA_ERROR'],
                'declaFamily' => ['ER_API_DECLA_00020', 'ER_API_DECLA_0042'],
                'uncatalogued' => ['ER_API_DECLA_0042', 'QUOTA_ERROR'],
            ],
            $points['error-codes']['value'],
        );
        static::assertEquals(
            ['POST /tiersdecl/v1/paje/employeurs/{numero}/ged/declarer 400' => 'list', 'GET /tiersdecl/v1/paje/anything 429' => 'object'],
            $points['error-body-shape']['value'],
        );
        static::assertEquals(
            ['POST /tiersdecl/v1/paje/ama/estimer' => ['amounts' => 1, 'txPas' => 2]],
            $points['response-decimals']['value'],
        );
        static::assertStringNotContainsString('never-recorded', json_encode($client->exchanges(), \JSON_THROW_ON_ERROR));
        static::assertStringNotContainsString(self::SECRET_NUMBER, json_encode($points, \JSON_THROW_ON_ERROR));
    }

    public function testHttpClientWithoutOverrideLeavesTheTokenRequestAlone(): void
    {
        $inner = new class implements ClientInterface {
            private ?RequestInterface $request = null;

            public function host(): ?string
            {
                return $this->request?->getUri()->getHost();
            }

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $this->request = $request;

                return new Response(200, [], '{}');
            }
        };
        $client = new SandboxHttpClient($inner, null);

        static::assertNull($client->last());
        $client->sendRequest(new Request('POST', 'https://api-edi.urssaf.fr/api/oauth/v1/token'));

        static::assertSame('api-edi.urssaf.fr', $inner->host());
        static::assertSame(SandboxReport::NOT_RUN, SandboxReport::points()['headers']['state']);
    }

    public function testReportPreSeedsEveryPointAndKeepsTheStrongestState(): void
    {
        static::assertCount(\count(SandboxReport::POINTS), SandboxReport::points());
        SandboxReport::record('token-host', SandboxReport::OBSERVED, 'active', 'a');
        SandboxReport::record('token-host', SandboxReport::NOT_OBSERVED, null, 'b');
        SandboxReport::record('headers', SandboxReport::NOT_OBSERVED, 'x', 'c');
        SandboxReport::record('headers', SandboxReport::OBSERVED, 'boolean', 'd');
        SandboxReport::merge('mandate-prerequisite', 'b', 2, 'e');
        SandboxReport::merge('mandate-prerequisite', 'a', 1, 'f');
        SandboxReport::describeRun('host.example.test', 'token.example.test', false);
        SandboxReport::flush();

        $report = $this->readReport();
        static::assertSame(1, $report['schema']);
        static::assertSame('1.5.2', $report['specVersion']);
        static::assertSame('host.example.test', $report['baseUriHost']);
        static::assertFalse($report['writesAllowed']);
        // @mago-expect analysis:mixed-assignment The decoded report is untyped: it is asserted to be an array right below.
        $points = $report['points'];
        static::assertIsArray($points);
        static::assertSame(['state' => 'observed', 'value' => 'active', 'test' => 'a'], $points['token-host']);
        static::assertSame(['state' => 'observed', 'value' => 'boolean', 'test' => 'd'], $points['headers']);
        static::assertSame(['state' => 'observed', 'value' => ['a' => 1, 'b' => 2], 'test' => 'f'], $points['mandate-prerequisite']);
        static::assertSame(['state' => 'not_run', 'value' => null, 'test' => ''], $points['quota-429']);
        static::assertCount(\count(SandboxReport::POINTS), $points);
    }

    public function testFictitiousDataBuildsValidDomainObjects(): void
    {
        static::assertSame('Z0000000000000', SandboxData::fictitiousEmployer()->number()->value());
        static::assertSame('Z0000000000001', SandboxData::fictitiousEmployeeNumber()->value());
        static::assertSame('TESTCHILD', SandboxData::fictitiousChild()->lastName()->value());
        static::assertSame(1, SandboxData::childminderEstimate()->childrenCount());
        static::assertSame(100, SandboxData::homeCareEstimate()->hours());
        static::assertNotEquals(SandboxData::newIdentity(), SandboxData::newIdentity());
        static::assertNotNull(SandboxData::completeCreation(SandboxData::newIdentity())->address());
        static::assertNull(SandboxData::creationWithoutAddress(SandboxData::newIdentity())->address());
        static::assertNull(SandboxData::creationWithoutActivity(SandboxData::newIdentity())->activityType());
        static::assertTrue(SandboxData::emptyCreation()->isEmpty());
        static::assertFalse(SandboxData::identityOnlyCreation(SandboxData::newIdentity())->isEmpty());
        static::assertSame(
            SandboxData::byNumber(SandboxData::fictitiousEmployeeNumber())->kind(),
            SandboxData::byNumber(SandboxData::fictitiousEmployeeNumber())->kind(),
        );
        static::assertSame(
            SandboxData::byIdentity(SandboxData::newIdentity())->kind(),
            SandboxData::byIdentity(SandboxData::newIdentity())->kind(),
        );
        static::assertSame('Europe/Paris', SandboxData::today()->getTimezone()->getName());

        $today = SandboxData::today();
        static::assertSame(10, SandboxData::childminderDeclaration(SandboxData::fictitiousEmployeeNumber(), SandboxData::fictitiousChild(), $today)->activityDays());
        static::assertSame('R', SandboxData::homeCareDeclaration(SandboxData::fictitiousEmployeeNumber(), SandboxData::fictitiousChild(), $today)->calculationMode()->value());
    }

    public function testDeclarationPeriodFollowsTheOpeningOfTheDeclarativeSpace(): void
    {
        $zone = new DateTimeZone('Europe/Paris');

        $beforeOpening = SandboxData::period(new DateTimeImmutable('2026-11-24 10:00:00', $zone));
        static::assertSame('2026-10-01', $beforeOpening->start()->toString());
        static::assertSame('2026-10-31', $beforeOpening->end()->toString());
        static::assertSame('2026-10-31', $beforeOpening->paymentDate()->toString());

        $afterOpening = SandboxData::period(new DateTimeImmutable('2026-11-25 10:00:00', $zone));
        static::assertSame('2026-11-01', $afterOpening->start()->toString());
        static::assertSame('2026-11-30', $afterOpening->end()->toString());
        static::assertSame('2026-11-25', $afterOpening->paymentDate()->toString());
    }

    public function testFailureMessagesNeverEchoUrssafTexts(): void
    {
        $rejected = UrssafRequestRejected::withErrors(
            Operation::VerifyEmployer,
            400,
            ApiErrors::of(
                ApiError::of('PARAM_INVALIDE', 'echoes ' . self::SECRET_NUMBER, 'description ' . self::SECRET_NUMBER),
                ApiError::of('free text ' . self::SECRET_NAME, null, null),
            ),
            RejectionKind::InvalidParameter,
        );

        $text = SandboxAssert::describe($rejected);

        static::assertStringContainsString('020', $text);
        static::assertStringContainsString('PARAM_INVALIDE', $text);
        static::assertStringNotContainsString(self::SECRET_NUMBER, $text);
        static::assertStringNotContainsString(self::SECRET_NAME, $text);
        static::assertSame(['PARAM_INVALIDE', 'other(length=' . mb_strlen('freetext' . self::SECRET_NAME) . ')'], SandboxAssert::codes($rejected->errors()));
        static::assertStringContainsString('URSSAF_SANDBOX_TOKEN_URI', SandboxAssert::describe(UrssafAuthenticationFailed::withStatus(401)));
        static::assertSame('RuntimeException', SandboxAssert::describe(new RuntimeException(self::SECRET_NUMBER)));
        static::assertSame([], SandboxAssert::uncatalogued(ApiErrors::of(ApiError::of('ER_API_DECLA_00020', null, null))));
        static::assertSame(['NOT_A_CODE'], SandboxAssert::uncatalogued(ApiErrors::of(ApiError::of('NOT_A_CODE', null, null))));
        SandboxAssert::assertCatalogued([]);
    }

    public function testOutcomeNamesTheStatusAndTheCodesOnly(): void
    {
        $rejected = UrssafRequestRejected::withErrors(
            Operation::DeclareHomeCare,
            400,
            ApiErrors::of(ApiError::of('ER_API_DECLA_00020', 'text', self::SECRET_NAME)),
            RejectionKind::DuplicateDeclaration,
        );
        $exchange = Exchange::from($this->employerRequest(), $this->richResponse());

        static::assertSame('rejected:400:ER_API_DECLA_00020', SandboxSession::outcome(['result' => null, 'rejected' => $rejected, 'exchange' => null]));
        static::assertSame('accepted:200', SandboxSession::outcome(['result' => true, 'rejected' => null, 'exchange' => $exchange]));
        static::assertSame('accepted:0', SandboxSession::outcome(['result' => true, 'rejected' => null, 'exchange' => null]));
        static::assertSame('ER_API_DECLA_00020', SandboxSession::codesOf($rejected));
        static::assertSame('', SandboxSession::codesOf(null));
    }

    private function employerRequest(): Request
    {
        return new Request(
            'GET',
            'https://host.example.test/tiersdecl/v1/paje/employeurs/' . self::SECRET_NUMBER . '/verifier?dtNaissanceEmployeur=' . self::SECRET_DATE . '&nirSalarie=' . self::SECRET_NIR,
            ['Authorization' => 'Bearer ' . self::AUTH_MARKER],
        );
    }

    private function richResponse(): Response
    {
        $body = '{"employeurAutoriseOk":true,"etatCompteEnLigne":"Coordonnées bancaires invalides","numeroPajeSalarie":"' . self::SECRET_NUMBER . '",'
            . '"declaration":{"mntCotisations":12.345,"mntSalaireNetImposable":500.1,"txPas":7.25,"nomEnfant":"' . self::SECRET_NAME . '"},'
            . '"erreursNonBloquantes":[{"code":"ER_API_023_NB_0000","message":"never kept","description":"' . self::SECRET_NAME . ' ' . self::SECRET_DATE . ' ' . self::SECRET_NIR . '"},'
            . '{"code":"ER_API_DECLA_NB_0010"}],"referenceDocumentaire":"' . self::SECRET_REFERENCE . '"}';

        return new Response(200, ['Content-Type' => 'application/json', 'X-Request-Id' => 'abc', 'Retry-After' => '30'], $body);
    }

    private function staticClient(ResponseInterface $response): ClientInterface
    {
        return new class ($response) implements ClientInterface {
            public function __construct(private readonly ResponseInterface $response) {}

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                return $this->response;
            }
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function readReport(): array
    {
        // @mago-expect analysis:mixed-assignment The decoded report is untyped: it is asserted to be an array right below.
        $decoded = json_decode((string) file_get_contents($this->reportPath), true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($decoded);

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
