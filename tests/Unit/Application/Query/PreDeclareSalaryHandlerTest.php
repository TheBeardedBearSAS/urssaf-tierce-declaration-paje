<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Query;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\ChildCheckView;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\NonBlockingMessageView;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\PreDeclarationView;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Exception\MandateRequired;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\RejectionKind;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Query\PreDeclareSalary;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Query\PreDeclareSalaryHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\Declaration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationAmounts;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\PreDeclarationResult;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\WithholdingTax;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Message\NonBlockingMessage;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Rate;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Fake\DeclarationWorld;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Fake\UnsupportedDeclaration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Declaration\RecordSamples;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\DomainAssertions;

final class PreDeclareSalaryHandlerTest extends TestCase
{
    public function testAChildminderDeclarationIsPreDeclaredWith045AndNothingIsRecorded(): void
    {
        $world = new DeclarationWorld();
        $result = self::preDeclarationResult();
        $world->gateway->queue('045', $result);

        $view = $this->handle($world, new PreDeclareSalary(DeclarationWorld::tenant(), DeclarationWorld::employer(), RecordSamples::childminder()));

        static::assertSame(['045'], $world->gateway->calls());
        static::assertSame('100.00', $view->amounts?->contributions);
        static::assertSame('5.5', $view->amounts->withholding->rate);
        static::assertTrue($view->pajemploiPlus);
        static::assertSame('2026-11-20', $view->transferDate);
        static::assertEquals(ChildCheckView::fromChecks($result->childChecks()), $view->childChecks);
        static::assertSame(['ER_API_DECLA_00007', 'W2'], array_map(static fn(NonBlockingMessageView $message): string => $message->code, $view->messages));
        static::assertSame(['call:045'], $world->journal->entries());
        static::assertSame([], $world->records->inner()->all());
        static::assertTrue(DeclarationWorld::employer()->equals($world->gateway->employers()[0]));
    }

    public function testAHomeCareDeclarationIsPreDeclaredWith046(): void
    {
        $world = new DeclarationWorld();
        $world->gateway->queue('046', PreDeclarationResult::of(null, false, null, [], []));

        $view = $this->handle($world, new PreDeclareSalary(DeclarationWorld::tenant(), DeclarationWorld::employer(), RecordSamples::homeCare()));

        static::assertSame(['046'], $world->gateway->calls());
        static::assertNull($view->amounts);
        static::assertFalse($view->pajemploiPlus);
        static::assertNull($view->transferDate);
        static::assertSame([], $view->childChecks);
        static::assertSame([], $view->messages);
    }

    public function testTheMandateGuardAppliesWithTheOperationOfTheVariant(): void
    {
        $world = new DeclarationWorld((new WorkflowPolicy())->withRequireLocalMandate(true));

        $childminder = $this->refusal($world, RecordSamples::childminder());
        $homeCare = $this->refusal($world, RecordSamples::homeCare());

        static::assertSame(['operation' => Operation::PreDeclareChildminder->value], $childminder->params());
        static::assertSame(['operation' => Operation::PreDeclareHomeCare->value], $homeCare->params());
        static::assertSame([], $world->gateway->calls());
    }

    public function testTheQueryCanSkipTheMandateCheck(): void
    {
        $world = new DeclarationWorld((new WorkflowPolicy())->withRequireLocalMandate(true));
        $world->gateway->queue('045', PreDeclarationResult::of(null, false, null, [], []));

        $this->handle($world, new PreDeclareSalary(DeclarationWorld::tenant(), DeclarationWorld::employer(), RecordSamples::childminder(), true));

        static::assertSame(['045'], $world->gateway->calls());
    }

    public function testARejectionReachesTheCaller(): void
    {
        $world = new DeclarationWorld();
        $rejection = UrssafRequestRejected::withErrors(Operation::PreDeclareChildminder, 400, ApiErrors::none(), RejectionKind::BusinessRule);
        $world->gateway->queue('045', $rejection);

        $this->expectExceptionObject($rejection);
        $this->handle($world, new PreDeclareSalary(DeclarationWorld::tenant(), DeclarationWorld::employer(), RecordSamples::childminder()));
    }

    public function testAnUnsupportedDeclarationIsRefusedBeforeAnyCall(): void
    {
        $world = new DeclarationWorld();

        try {
            $this->handle($world, new PreDeclareSalary(DeclarationWorld::tenant(), DeclarationWorld::employer(), new UnsupportedDeclaration()));
            static::fail('Expected an InvariantViolation.');
        } catch (InvariantViolation $unsupported) {
            static::assertSame('pre_declaration.unsupported', $unsupported->violationCode());
        }

        static::assertSame([], $world->gateway->calls());
    }

    public function testTheQueryMasksItsPersonalData(): void
    {
        DomainAssertions::assertSensitiveParameters(PreDeclareSalary::class, '__construct', ['employer', 'declaration']);
        static::assertFalse((new PreDeclareSalary(DeclarationWorld::tenant(), DeclarationWorld::employer(), RecordSamples::childminder()))->skipMandateCheck);
    }

    private static function preDeclarationResult(): PreDeclarationResult
    {
        return PreDeclarationResult::of(
            DeclarationAmounts::of(
                RecordSamples::amount('100'),
                null,
                null,
                WithholdingTax::of(null, Rate::fromString('5.5'), null, null),
                null,
                null,
                null,
                null,
                null,
                null,
            ),
            true,
            RecordSamples::date('2026-11-20'),
            RecordSamples::result()->childChecks(),
            [NonBlockingMessage::of('ER_API_DECLA_00007', 'Message', 'Description'), NonBlockingMessage::of('W2', null, null)],
        );
    }

    private function handle(DeclarationWorld $world, PreDeclareSalary $query): PreDeclarationView
    {
        return (new PreDeclareSalaryHandler($world->gateway, $world->guard()))($query);
    }

    private function refusal(DeclarationWorld $world, Declaration $declaration): MandateRequired
    {
        try {
            $this->handle($world, new PreDeclareSalary(DeclarationWorld::tenant(), DeclarationWorld::employer(), $declaration));
        } catch (MandateRequired $refused) {
            return $refused;
        }
        static::fail('Expected a MandateRequired.');
    }
}
