<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Dto;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\EmployerAccountView;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\EmployerErasure;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\EmployerSelector;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\EmployerStatusView;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\MandateView;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\NonBlockingMessageView;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\PajeEmployerNumber;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Mandate\MandateState;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Message\NonBlockingMessage;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\Instant;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Employer\EmployerSamples;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Mandate\MandateSamples;

final class EmployerViewsTest extends TestCase
{
    public function testStatusViewOfAVerification(): void
    {
        $view = EmployerStatusView::fromVerification(EmployerSamples::verification(false, 'Zzz'), Instant::fromIso8601('2026-11-17T09:00:00Z'));

        static::assertFalse($view->authorised);
        static::assertSame('Zzz', $view->onlineAccountState);
        static::assertSame('2026-11-17T09:00:00Z', $view->checkedAt);
        static::assertNull(EmployerStatusView::fromVerification(EmployerSamples::verification(), null)->checkedAt);
    }

    public function testStatusViewOfAnAccountIsNullUntilItWasChecked(): void
    {
        $account = EmployerSamples::account();

        static::assertNull(EmployerStatusView::fromAccount($account));

        $account->applyVerification(EmployerSamples::verification(true, 'Actif'), Instant::fromIso8601('2026-11-16T09:00:00Z'));
        $view = EmployerStatusView::fromAccount($account);

        static::assertNotNull($view);
        static::assertTrue($view->authorised);
        static::assertSame('2026-11-16T09:00:00Z', $view->checkedAt);
    }

    public function testMandateViewKeepsNoEmployerData(): void
    {
        $view = MandateView::fromMandate(MandateSamples::inState(MandateState::Cancelled));

        static::assertSame('mandate-1', $view->mandateId);
        static::assertSame('tenant-1', $view->tenantId);
        static::assertSame('employer-ref-1', $view->externalReference);
        static::assertSame('account-1', $view->employerAccountId);
        static::assertSame('registered', $view->origin);
        static::assertSame('cancelled', $view->state);
        static::assertSame('2026-11-15T10:00:00Z', $view->registeredAt);
        static::assertSame('2026-11-15T12:00:00Z', $view->cancelledAt);
        static::assertSame('2026-11-15T12:00:00Z', $view->updatedAt);
        static::assertStringNotContainsString('Y1869005780009', print_r($view, true));
        static::assertNull(MandateView::fromMandate(MandateSamples::registered())->cancelledAt);
    }

    public function testAccountViewMasksTheEmployerNumberInDebugOutput(): void
    {
        $view = EmployerAccountView::fromAccount(EmployerSamples::account(), MandateSamples::registered());

        static::assertSame('Y1869005780009', $view->employerNumber);
        static::assertSame('mandate-1', $view->mandate?->mandateId);
        static::assertSame('tracked', $view->origin);
        static::assertSame('tracked', $view->stage);
        static::assertSame('2026-11-15T10:00:00Z', $view->createdAt);
        static::assertSame('2026-11-15T10:00:00Z', $view->updatedAt);
        $dump = print_r($view, true);
        static::assertStringNotContainsString('Y1869005780009', $dump);
        static::assertSame(
            [
                'accountId' => 'account-1',
                'tenantId' => 'tenant-1',
                'externalReference' => 'employer-ref-1',
                'employerNumber' => '***',
                'origin' => 'tracked',
                'stage' => 'tracked',
            ],
            $view->__debugInfo(),
        );
    }

    public function testSelectorsKeepTheirReferenceAndMaskIt(): void
    {
        $id = EmployerAccountId::fromString('account-1');
        $reference = ExternalReference::fromString('employer-ref-1');
        $number = PajeEmployerNumber::of('Y1869005780009');

        static::assertSame($id, EmployerSelector::byAccountId($id)->reference);
        static::assertSame($reference, EmployerSelector::byExternalReference($reference)->reference);
        static::assertSame($number, EmployerSelector::byEmployerNumber($number)->reference);
        static::assertStringNotContainsString('Y1869005780009', print_r(EmployerSelector::byEmployerNumber($number), true));
        static::assertSame(['reference' => '***'], EmployerSelector::byEmployerNumber($number)->__debugInfo());
    }

    public function testErasureKeepsIdentifiers(): void
    {
        $erasure = new EmployerErasure('account-1', ['mandate-1'], ['record-1', 'record-2']);

        static::assertSame('account-1', $erasure->accountId);
        static::assertSame(['mandate-1'], $erasure->mandateIds);
        static::assertSame(['record-1', 'record-2'], $erasure->declarationRecordIds);
    }

    public function testMessageViewMasksItsDescription(): void
    {
        $views = NonBlockingMessageView::fromMessages([
            NonBlockingMessage::of('CODE_1', 'message', 'secret description'),
            NonBlockingMessage::of('CODE_2', null, null),
        ]);

        static::assertSame(['CODE_1', 'CODE_2'], array_map(static fn(NonBlockingMessageView $view): string => $view->code, $views));
        static::assertSame('secret description', $views[0]->description);
        static::assertSame([0, 1], array_keys($views));
        static::assertStringNotContainsString('secret description', print_r($views[0], true));
        static::assertSame(['code' => 'CODE_1', 'message' => 'message', 'description' => '***'], $views[0]->__debugInfo());
        static::assertSame(['code' => 'CODE_2', 'message' => null, 'description' => null], $views[1]->__debugInfo());
    }
}
