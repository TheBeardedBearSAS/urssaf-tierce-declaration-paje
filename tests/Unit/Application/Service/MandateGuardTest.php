<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Exception\MandateRequired;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\PajeEmployerNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Instant;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Fake\DeclarationWorld;

final class MandateGuardTest extends TestCase
{
    private const NUMBER = 'Y1869005780009';

    public function testTheGuardIsOffByDefault(): void
    {
        $world = new DeclarationWorld();

        $world->guard()->assertMandate(DeclarationWorld::tenant(), PajeEmployerNumber::of(self::NUMBER), Operation::DeclareChildminder, false);

        $this->expectNotToPerformAssertions();
    }

    public function testTheSkipFlagBypassesTheGuard(): void
    {
        $world = $this->guarded();

        $world->guard()->assertMandate(DeclarationWorld::tenant(), PajeEmployerNumber::of(self::NUMBER), Operation::DeclareChildminder, true);

        $this->expectNotToPerformAssertions();
    }

    public function testAnActiveLocalMandateIsEnough(): void
    {
        $world = $this->guarded();
        $world->registeredMandate();

        $world->guard()->assertMandate(DeclarationWorld::tenant(), PajeEmployerNumber::of(self::NUMBER), Operation::DeclareChildminder, false);

        $this->expectNotToPerformAssertions();
    }

    public function testWithoutMandateTheOperationIsRefusedWithoutTheEmployerNumber(): void
    {
        $world = $this->guarded();

        $refused = $this->refusal($world, Operation::DeclareHomeCare);

        static::assertSame('mandate.required', $refused->violationCode());
        static::assertSame(['operation' => '051'], $refused->params());
        static::assertStringContainsString('method 051', $refused->getMessage());
        static::assertStringNotContainsString(self::NUMBER, $refused->getMessage());
    }

    public function testACancelledMandateIsRefused(): void
    {
        $world = $this->guarded();
        $mandate = $world->registeredMandate();
        $mandate->cancel(Instant::fromIso8601(DeclarationWorld::NOW));
        $mandate->releaseEvents();
        $world->mandates->save($mandate);

        static::assertSame('mandate.required', $this->refusal($world, Operation::PreDeclareChildminder)->violationCode());
    }

    public function testAMandateOfAnotherEmployerOrTenantDoesNotCount(): void
    {
        $world = $this->guarded();
        $world->registeredMandate('Y1869005780010');
        $other = $this->guarded();
        $other->registeredMandate();

        static::assertSame('mandate.required', $this->refusal($world, Operation::DeclareChildminder)->violationCode());
        $this->expectException(MandateRequired::class);
        $other->guard()->assertMandate(TenantId::fromString('tenant-2'), PajeEmployerNumber::of(self::NUMBER), Operation::DeclareChildminder, false);
    }

    /**
     * @return iterable<string, array{Operation}>
     */
    public static function guardedOperations(): iterable
    {
        yield '045' => [Operation::PreDeclareChildminder];
        yield '046' => [Operation::PreDeclareHomeCare];
        yield '050' => [Operation::DeclareChildminder];
        yield '051' => [Operation::DeclareHomeCare];
    }

    #[DataProvider('guardedOperations')]
    public function testTheRefusalNamesTheOperation(Operation $operation): void
    {
        static::assertSame($operation->value, $this->refusal($this->guarded(), $operation)->params()['operation']);
    }

    private function guarded(): DeclarationWorld
    {
        return new DeclarationWorld((new WorkflowPolicy())->withRequireLocalMandate(true));
    }

    private function refusal(DeclarationWorld $world, Operation $operation): MandateRequired
    {
        try {
            $world->guard()->assertMandate(DeclarationWorld::tenant(), PajeEmployerNumber::of(self::NUMBER), $operation, false);
        } catch (MandateRequired $refused) {
            return $refused;
        }
        static::fail('Expected the guard to refuse.');
    }
}
