<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Contract;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Child\Child;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Child\ChildEntitlement;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employee\EmployeeAccount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerVerification;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\OnlineAccountStateValue;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Estimate\ContributionEstimate;

/**
 * Read-only operations against the URSSAF sandbox: token, 020, 021, 022, 040 and 041 (nothing is stored). Hard
 * assertions cover what the SDK relies on; the open points of docs/api-reference.md section 5 are observed into the
 * report (statuses, codes, booleans and shapes only: never a number, a name, a date or a text of the API).
 *
 * One method per operation and per observed point, hence the method count.
 *
 * @mago-expect lint:too-many-methods
 */
#[Group('contract')]
final class ReadOnlySandboxContractTest extends TestCase
{
    private SandboxSession $session;

    private ?SandboxSession $started = null;

    public static function tearDownAfterClass(): void
    {
        SandboxReport::flush();
    }

    protected function tearDown(): void
    {
        // Null when the test was skipped: no session was built.
        $this->started?->assertCodesCatalogued();
    }

    protected function setUp(): void
    {
        $this->started = SandboxSession::start(self::class, false);
        $this->session = $this->started;
    }

    public function testTokenEndpointOfTheSandboxHostIssuesAToken(): void
    {
        $derived = $this->session->sandbox->rawTokenProbe($this->session->sandbox->configuration->tokenUri());
        $chosen = $derived;
        $override = null;
        $used = 'derived';
        if (!$derived->isSuccessful() && null !== $this->session->sandbox->tokenUriOverride) {
            $override = $this->session->sandbox->rawTokenProbe($this->session->sandbox->tokenUriOverride);
            if ($override->isSuccessful()) {
                $chosen = $override;
                $used = 'override';
            }
        }

        $this->session->observe('token-host', [
            'derived' => $derived->status,
            'override' => $override?->status,
            'used' => $used,
            'tokenType' => $chosen->enumValues['token_type'] ?? 'absent',
        ]);

        $message = \sprintf(
            'Token request answered %d by %s%s. %s',
            $derived->status,
            $derived->host,
            $override instanceof Exchange ? \sprintf(', %d by %s (override)', $override->status, $override->host) : '',
            SandboxAssert::authenticationHint(),
        );
        static::assertSame(200, $chosen->status, $message);
        static::assertSame(Exchange::OBJECT, $chosen->bodyShape, $message);
        static::assertContains('access_token', $chosen->bodyKeys);
        static::assertContains('expires_in', $chosen->bodyKeys);
        static::assertContains($chosen->jsonTypes['expires_in'] ?? '', ['integer', 'float']);
    }

    public function testVerifiesTheTestEmployer(): void
    {
        $call = $this->verifyEmployer($this->session->requireEmployer());

        $result = $call['result'];
        static::assertInstanceOf(EmployerVerification::class, $result, 'The employer verification was rejected: ' . SandboxSession::codesOf($call['rejected']));
        $exchange = $call['exchange'];
        static::assertInstanceOf(Exchange::class, $exchange);
        static::assertSame(200, $exchange->status);
        static::assertSame(Exchange::OBJECT, $exchange->bodyShape);
        $this->session->observeKey('online-account-state', '020', [
            'wire' => $exchange->enumValues['etatCompteEnLigne'] ?? 'absent',
            'documented' => $result->state()->documented() instanceof OnlineAccountStateValue,
            'authorised' => $result->authorised(),
            'authorisedType' => $exchange->jsonTypes['employeurAutoriseOk'] ?? 'absent',
        ]);
    }

    public function testVerifyingAnUnknownEmployerIsRefusedOrAnswered(): void
    {
        $call = $this->verifyEmployer(SandboxData::fictitiousEmployer());

        $rejected = $call['rejected'];
        static::assertTrue(
            $call['result'] instanceof EmployerVerification || $rejected instanceof UrssafRequestRejected,
            'Neither a verification nor a catalogued rejection.',
        );
        if ($rejected instanceof UrssafRequestRejected) {
            static::assertSame(400, $rejected->httpStatus(), 'An unknown employer must be a business refusal (400).');
        }
        $this->session->observe('unknown-employer-020', SandboxSession::outcome($call));
    }

    public function testVerifiesTheTestEmployee(): void
    {
        $employee = $this->session->requireEmployee();
        $call = $this->session->attempt(fn(): EmployeeAccount => $this->session->sandbox->gateway->verifyEmployee(
            $this->session->sandbox->tenantId,
            SandboxData::byNumber($employee),
        ));

        $result = $call['result'];
        static::assertInstanceOf(EmployeeAccount::class, $result, 'The employee verification was rejected: ' . SandboxSession::codesOf($call['rejected']));
        $exchange = $call['exchange'];
        static::assertInstanceOf(Exchange::class, $exchange);
        static::assertSame(200, $exchange->status);
        static::assertSame(Exchange::OBJECT, $exchange->bodyShape);
        static::assertNotNull($result->employeeNumber(), 'The answer carries no employee number.');
        $this->session->observeKey('online-account-state', '021', [
            'wire' => $exchange->enumValues['etatCompteEnLigne'] ?? 'absent',
            'documented' => $result->state()->documented() instanceof OnlineAccountStateValue,
            'numberType' => $exchange->jsonTypes['numeroPajeSalarie'] ?? 'absent',
            'childminder' => $result->childminder(),
            'homeCare' => $result->homeCare(),
        ]);
    }

    public function testVerifyingAnUnknownEmployeeIsRefusedOrAnswered(): void
    {
        $call = $this->session->attempt(fn(): EmployeeAccount => $this->session->sandbox->gateway->verifyEmployee(
            $this->session->sandbox->tenantId,
            SandboxData::byNumber(SandboxData::fictitiousEmployeeNumber()),
        ));

        $rejected = $call['rejected'];
        static::assertTrue(
            $call['result'] instanceof EmployeeAccount || $rejected instanceof UrssafRequestRejected,
            'Neither an account nor a catalogued rejection.',
        );
        $this->session->observe('unknown-employee-021', SandboxSession::outcome($call));
    }

    public function testVerifiesTheEntitledChildren(): void
    {
        $employer = $this->session->requireEmployer();
        $child = $this->session->requireChild();
        $call = $this->session->attempt(fn(): array => $this->session->sandbox->gateway->verifyChildren(
            $this->session->sandbox->tenantId,
            $employer,
            [$child],
        ));

        $checks = $call['result'];
        static::assertIsArray($checks, 'The children verification was rejected: ' . SandboxSession::codesOf($call['rejected']));
        static::assertCount(1, $checks, 'One check is expected per requested child.');
        $this->session->observe('children-022', [
            'count' => \count($checks),
            'statusRaw' => $checks[0]->status()->raw(),
            'documented' => $checks[0]->status()->documented() instanceof ChildEntitlement,
            'childEchoed' => $checks[0]->child() instanceof Child,
        ]);
    }

    public function testEstimatesAChildminderActivity(): void
    {
        $call = $this->session->attempt(fn(): ContributionEstimate => $this->session->sandbox->gateway->estimateChildminder(
            $this->session->sandbox->tenantId,
            SandboxData::childminderEstimate(),
        ));

        $this->session->observeKey('estimate-context-free', '040', SandboxSession::outcome($call));
        static::assertInstanceOf(ContributionEstimate::class, $call['result'], 'The estimate was rejected: ' . SandboxSession::codesOf($call['rejected']));
        $exchange = $call['exchange'];
        static::assertInstanceOf(Exchange::class, $exchange);
        static::assertSame(200, $exchange->status);
        static::assertSame(Exchange::OBJECT, $exchange->bodyShape);
    }

    public function testEstimatesAHomeCareActivity(): void
    {
        $call = $this->session->attempt(fn(): ContributionEstimate => $this->session->sandbox->gateway->estimateHomeCare(
            $this->session->sandbox->tenantId,
            SandboxData::homeCareEstimate(),
        ));

        $this->session->observeKey('estimate-context-free', '041', SandboxSession::outcome($call));
        static::assertInstanceOf(ContributionEstimate::class, $call['result'], 'The estimate was rejected: ' . SandboxSession::codesOf($call['rejected']));
        $exchange = $call['exchange'];
        static::assertInstanceOf(Exchange::class, $exchange);
        static::assertSame(200, $exchange->status);
        static::assertSame(Exchange::OBJECT, $exchange->bodyShape);
    }

    /**
     * @return array{result: null|EmployerVerification, rejected: null|UrssafRequestRejected, exchange: null|Exchange}
     */
    private function verifyEmployer(EmployerReference $employer): array
    {
        return $this->session->attempt(fn(): EmployerVerification => $this->session->sandbox->gateway->verifyEmployer(
            $this->session->sandbox->tenantId,
            $employer,
        ));
    }
}
