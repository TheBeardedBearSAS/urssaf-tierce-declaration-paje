<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Child\ChildEntitlementStatus;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\CalculationMode;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeByAdministrativeData;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeByNir;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeByNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeCreation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\Nir;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\PajeEmployeeNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\OnlineAccountStateValue;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Estimate\ChildminderEstimateRequest;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Estimate\HomeCareEstimateRequest;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Estimate\YoungestChildAge;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Amount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\HalfDays;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\PostalCode;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\EmployeeWireSamples;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\Fixtures;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\Stack;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Support\Stacks;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Infrastructure\Urssaf\Wire\DeclarationWireSamples;

/**
 * What each of the twelve operations sends (method, path, query, body, against the official examples) and how it
 * decodes the official answer (ADR 0002). Failures are in {@see UrssafHttpGatewayFailureTest}.
 *
 * One method per operation, hence the method count.
 *
 * @mago-expect lint:too-many-methods
 */
final class UrssafHttpGatewayTest extends TestCase
{
    private const BASE = '/tiersdecl/v1/paje';
    private const EMPLOYER = '/employeurs/Y1869005780009';

    private static function answering(string $body, int $status = 200): Stack
    {
        $stack = Stacks::sandbox();
        $stack->warmUpToken();
        $stack->respond($status, $body);

        return $stack;
    }

    /**
     * The only API call of the stack (the token was warmed up before).
     */
    private static function sent(Stack $stack): RequestInterface
    {
        return $stack->lastRequest();
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function query(RequestInterface $request): array
    {
        $query = [];
        parse_str($request->getUri()->getQuery(), $query);

        return $query;
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function body(RequestInterface $request): array
    {
        return Fixtures::decode((string) $request->getBody());
    }

    /**
     * Same values (165 equals 165.00) and same key order, at every level, as the official example.
     *
     * @param array<array-key, mixed> $actual
     */
    private static function assertBodyIsFixture(string $fixture, array $actual): void
    {
        self::assertSameDocument(Fixtures::json($fixture), $actual);
    }

    /**
     * @param array<array-key, mixed> $expected
     * @param array<array-key, mixed> $actual
     */
    private static function assertSameDocument(array $expected, array $actual): void
    {
        static::assertEquals($expected, $actual);
        static::assertSame(self::keyOrder($expected), self::keyOrder($actual));
    }

    /**
     * @param array<array-key, mixed> $value
     *
     * @return array<array-key, mixed>
     */
    private static function keyOrder(array $value): array
    {
        return array_map(static fn(mixed $item): ?array => \is_array($item) ? self::keyOrder($item) : null, $value);
    }

    private static function assertNoBody(RequestInterface $request): void
    {
        static::assertSame('', (string) $request->getBody());
        static::assertFalse($request->hasHeader('Content-Type'));
    }

    private static function childminderEstimate(): ChildminderEstimateRequest
    {
        return ChildminderEstimateRequest::of(
            PostalCode::fromString('75000'),
            PostalCode::fromString('75000'),
            Amount::fromDecimalString('165'),
            1,
            10,
            HalfDays::fromHalfDays(2),
            0,
            0,
        );
    }

    private static function homeCareEstimate(): HomeCareEstimateRequest
    {
        return HomeCareEstimateRequest::of(
            PostalCode::fromString('75000'),
            PostalCode::fromString('75000'),
            Amount::fromDecimalString('165'),
            CalculationMode::real(),
            10,
            0,
            0,
            YoungestChildAge::underThree(),
            true,
        );
    }

    public function testVerifyEmployer(): void
    {
        $stack = self::answering(Fixtures::load('verify_employer_response'));

        $result = $stack->gateway()->verifyEmployer(Stacks::tenant(), EmployeeWireSamples::employer());

        $request = self::sent($stack);
        static::assertSame('GET', $request->getMethod());
        static::assertSame(self::BASE . self::EMPLOYER . '/verifier', $request->getUri()->getPath());
        static::assertSame(['dtNaissanceEmployeur' => '1975-02-23'], self::query($request));
        self::assertNoBody($request);
        static::assertSame(['tenant-1:020'], $stack->rateLimiter->acquired());
        static::assertTrue($result->authorised());
        static::assertSame(OnlineAccountStateValue::Active, $result->state()->documented());
    }

    public function testVerifyEmployee(): void
    {
        $stack = self::answering(Fixtures::load('verify_employee_response'));
        $identification = EmployeeByNir::of(Nir::of('178010701005265'), CalendarDate::fromString('1979-01-01'));

        $account = $stack->gateway()->verifyEmployee(Stacks::tenant(), $identification);

        $request = self::sent($stack);
        static::assertSame('POST', $request->getMethod());
        static::assertSame(self::BASE . '/salaries/verifier', $request->getUri()->getPath());
        static::assertSame(['nirSalarie' => '178010701005265', 'dtNaissanceSalarie' => '1979-01-01'], self::query($request));
        static::assertSame(trim(Fixtures::load('verify_employee_request')), (string) $request->getBody());
        static::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        static::assertSame(['tenant-1:021'], $stack->rateLimiter->acquired());
        static::assertSame('string', $account->employeeNumber());
        static::assertTrue($account->childminder());
        static::assertTrue($account->homeCare());
    }

    public function testVerifyEmployeeByAdministrativeDataSendsTheIdentityAsBody(): void
    {
        $stack = self::answering('{}');
        $identification = EmployeeByAdministrativeData::of(EmployeeWireSamples::identity(), CalendarDate::fromString('1980-01-30'));

        $stack->gateway()->verifyEmployee(Stacks::tenant(), $identification);

        $request = self::sent($stack);
        static::assertSame(['dtNaissanceSalarie' => '1980-01-30'], self::query($request));
        static::assertSame(
            Fixtures::section('associate_employee_request_valid', 'inputIdentificationSalarie'),
            self::body($request),
        );
    }

    public function testVerifyChildren(): void
    {
        $stack = self::answering(Fixtures::load('verify_children_response'));

        $checks = $stack->gateway()->verifyChildren(Stacks::tenant(), EmployeeWireSamples::employer(), [EmployeeWireSamples::child()]);

        $request = self::sent($stack);
        static::assertSame('POST', $request->getMethod());
        static::assertSame(self::BASE . self::EMPLOYER . '/enfants/verifier', $request->getUri()->getPath());
        static::assertSame(['dtNaissanceEmployeur' => '1975-02-23'], self::query($request));
        self::assertBodyIsFixture('verify_children_request', self::body($request));
        static::assertSame(['tenant-1:022'], $stack->rateLimiter->acquired());
        static::assertCount(1, $checks);
        static::assertSame(ChildEntitlementStatus::of(1)->raw(), $checks[0]->status()->raw());
        static::assertSame('2015-01-12', $checks[0]->child()?->birthDate()->toString());
    }

    public function testAssociateEmployee(): void
    {
        $stack = self::answering(Fixtures::load('associate_employee_response'));
        $employee = EmployeeByNumber::of(PajeEmployeeNumber::of('Y2926822480004'), CalendarDate::fromString('1980-01-30'));

        $association = $stack->gateway()->associateEmployee(
            Stacks::tenant(),
            EmployeeWireSamples::employer(),
            $employee,
            EmployeeWireSamples::creation(),
        );

        $request = self::sent($stack);
        static::assertSame('POST', $request->getMethod());
        static::assertSame(self::BASE . self::EMPLOYER . '/salarie/associer', $request->getUri()->getPath());
        static::assertSame(
            ['dtNaissanceEmployeur' => '1975-02-23', 'numeroPajeSalarie' => 'Y2926822480004', 'dtNaissanceSalarie' => '1980-01-30'],
            self::query($request),
        );
        $official = Fixtures::json('associate_employee_request_valid');
        unset($official['numeroPajeSalarie']);
        self::assertSameDocument($official, self::body($request));
        static::assertSame(['tenant-1:023'], $stack->rateLimiter->acquired());
        static::assertTrue($association->created());
        static::assertTrue($association->associated());
        static::assertFalse($association->alreadyAssociated());
    }

    public function testAssociateEmployeeAlwaysSendsABody(): void
    {
        $stack = self::answering('{"salarieDejaAssocieOK":true}');

        $association = $stack->gateway()->associateEmployee(
            Stacks::tenant(),
            EmployeeWireSamples::employer(),
            EmployeeByNir::of(Nir::of('178010701005265')),
            EmployeeCreation::of(null, null, null, null, null, null),
        );

        static::assertSame('{}', (string) self::sent($stack)->getBody());
        static::assertTrue($association->alreadyAssociated());
    }

    public function testRegisterMandate(): void
    {
        $stack = self::answering('');

        $stack->gateway()->registerMandate(Stacks::tenant(), EmployeeWireSamples::employer());

        $request = self::sent($stack);
        static::assertSame('POST', $request->getMethod());
        static::assertSame(self::BASE . '/mandats', $request->getUri()->getPath());
        static::assertSame(['numeroPaje' => 'Y1869005780009', 'dateNaissance' => '1975-02-23'], self::query($request));
        self::assertNoBody($request);
        static::assertSame(['tenant-1:030'], $stack->rateLimiter->acquired());
    }

    public function testCancelMandate(): void
    {
        $stack = self::answering('{}');

        $stack->gateway()->cancelMandate(Stacks::tenant(), EmployeeWireSamples::employer());

        $request = self::sent($stack);
        static::assertSame('DELETE', $request->getMethod());
        static::assertSame(self::BASE . '/mandats', $request->getUri()->getPath());
        static::assertSame(['numeroPaje' => 'Y1869005780009', 'dateNaissance' => '1975-02-23'], self::query($request));
        self::assertNoBody($request);
        static::assertSame(['tenant-1:031'], $stack->rateLimiter->acquired());
    }

    public function testEstimateChildminder(): void
    {
        $stack = self::answering(Fixtures::load('estimate_response'));

        $estimate = $stack->gateway()->estimateChildminder(Stacks::tenant(), self::childminderEstimate());

        $request = self::sent($stack);
        static::assertSame('POST', $request->getMethod());
        static::assertSame(self::BASE . '/ama/estimer', $request->getUri()->getPath());
        static::assertSame('', $request->getUri()->getQuery());
        self::assertBodyIsFixture('estimate_ama_request', self::body($request));
        static::assertSame(['tenant-1:040'], $stack->rateLimiter->acquired());
        static::assertSame('0.00', $estimate->employerShare()?->toDecimalString());
        static::assertCount(1, $estimate->messages());
    }

    public function testEstimateHomeCare(): void
    {
        $stack = self::answering(Fixtures::load('estimate_response'));

        $estimate = $stack->gateway()->estimateHomeCare(Stacks::tenant(), self::homeCareEstimate());

        $request = self::sent($stack);
        static::assertSame('POST', $request->getMethod());
        static::assertSame(self::BASE . '/ged/estimer', $request->getUri()->getPath());
        static::assertSame('', $request->getUri()->getQuery());
        self::assertBodyIsFixture('estimate_ged_request', self::body($request));
        static::assertSame(['tenant-1:041'], $stack->rateLimiter->acquired());
        static::assertSame('0.00', $estimate->employerExemption()?->toDecimalString());
    }

    public function testPreDeclareChildminder(): void
    {
        $stack = self::answering(Fixtures::load('predeclare_response_valid'));

        $result = $stack->gateway()->preDeclareChildminder(
            Stacks::tenant(),
            EmployeeWireSamples::employer(),
            DeclarationWireSamples::preDeclareChildminder(),
        );

        $request = self::sent($stack);
        static::assertSame('POST', $request->getMethod());
        static::assertSame(self::BASE . self::EMPLOYER . '/ama/predeclarer', $request->getUri()->getPath());
        static::assertSame(['dtNaissanceEmployeur' => '1975-02-23'], self::query($request));
        self::assertBodyIsFixture('predeclare_ama_request_valid', self::body($request));
        static::assertSame(['tenant-1:045'], $stack->rateLimiter->acquired());
        static::assertSame('2020-01-01', $result->amounts()?->plannedDebitDate()?->toString());
        static::assertCount(1, $result->childChecks());
    }

    public function testPreDeclareHomeCare(): void
    {
        $stack = self::answering(Fixtures::load('predeclare_response_valid'));

        $result = $stack->gateway()->preDeclareHomeCare(
            Stacks::tenant(),
            EmployeeWireSamples::employer(),
            DeclarationWireSamples::homeCare(),
        );

        $request = self::sent($stack);
        static::assertSame('POST', $request->getMethod());
        static::assertSame(self::BASE . self::EMPLOYER . '/ged/predeclarer', $request->getUri()->getPath());
        static::assertSame(['dtNaissanceEmployeur' => '1975-02-23'], self::query($request));
        self::assertBodyIsFixture('predeclare_ged_request_valid', self::body($request));
        static::assertSame(['tenant-1:046'], $stack->rateLimiter->acquired());
        static::assertSame('2020-01-31', $result->amounts()?->plannedPaymentDate()?->toString());
    }

    public function testDeclareChildminder(): void
    {
        $stack = self::answering(Fixtures::load('declare_response_valid'));

        $result = $stack->gateway()->declareChildminder(
            Stacks::tenant(),
            EmployeeWireSamples::employer(),
            DeclarationWireSamples::declareChildminder(),
        );

        $request = self::sent($stack);
        static::assertSame('POST', $request->getMethod());
        static::assertSame(self::BASE . self::EMPLOYER . '/ama/declarer', $request->getUri()->getPath());
        static::assertSame(['dtNaissanceEmployeur' => '1975-02-23'], self::query($request));
        self::assertBodyIsFixture('declare_ama_request_valid', self::body($request));
        static::assertSame(['tenant-1:050'], $stack->rateLimiter->acquired());
        static::assertSame('string', $result->documentReference());
        static::assertSame('2020-01-01', $result->amounts()?->plannedDebitDate()?->toString());
    }

    public function testDeclareHomeCare(): void
    {
        $stack = self::answering(Fixtures::load('declare_response_valid'));

        $result = $stack->gateway()->declareHomeCare(
            Stacks::tenant(),
            EmployeeWireSamples::employer(),
            DeclarationWireSamples::homeCare(),
        );

        $request = self::sent($stack);
        static::assertSame('POST', $request->getMethod());
        static::assertSame(self::BASE . self::EMPLOYER . '/ged/declarer', $request->getUri()->getPath());
        static::assertSame(['dtNaissanceEmployeur' => '1975-02-23'], self::query($request));
        self::assertBodyIsFixture('declare_ged_request_valid', self::body($request));
        static::assertSame(['tenant-1:051'], $stack->rateLimiter->acquired());
        static::assertSame('string', $result->documentReference());
    }
}
