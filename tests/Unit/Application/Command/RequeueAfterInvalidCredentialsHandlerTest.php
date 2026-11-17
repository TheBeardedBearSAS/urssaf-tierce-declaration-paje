<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Command;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\RequeueAfterInvalidCredentials;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\RequeueAfterInvalidCredentialsHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\RequeueReport;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Fake\DeclarationWorld;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Declaration\RecordSamples;

final class RequeueAfterInvalidCredentialsHandlerTest extends TestCase
{
    public function testTheDeclarationsOfTheTenantAreMovedBackToPendingInOneTransaction(): void
    {
        $world = new DeclarationWorld();
        $world->recordWith('record-1', DeclarationState::InvalidCredentials, 1, RecordSamples::childminder(), 'ref-1', 'tenant-1', 'Y1869005780009');
        $world->recordWith('record-2', DeclarationState::InvalidCredentials, 1, RecordSamples::childminder(), 'ref-2', 'tenant-1', 'Y1869005780009');
        $world->recordWith('record-3', DeclarationState::InvalidCredentials, 1, RecordSamples::childminder(), 'ref-3', 'tenant-2', 'Y1869005780009');
        $world->recordWith('record-4', DeclarationState::Error, 1, RecordSamples::childminder(), 'ref-4', 'tenant-1', 'Y1869005780009');

        $report = $this->requeue($world, null);

        static::assertSame(2, $report->declarations);
        static::assertSame(DeclarationState::Pending, $world->records->get(DeclarationRecordId::fromString('record-1'))->state());
        static::assertSame(DeclarationState::Pending, $world->records->get(DeclarationRecordId::fromString('record-2'))->state());
        static::assertSame(DeclarationState::InvalidCredentials, $world->records->get(DeclarationRecordId::fromString('record-3'))->state());
        static::assertSame(DeclarationState::Error, $world->records->get(DeclarationRecordId::fromString('record-4'))->state());
        static::assertSame(1, $world->transactions->committed());
        static::assertSame(
            ['begin', 'save:record-1', 'save:record-2', 'append:', 'commit', 'publish:DeclarationRequeued', 'publish:DeclarationRequeued'],
            $world->journal->entries(),
        );
        static::assertSame(DeclarationWorld::NOW, $world->records->get(DeclarationRecordId::fromString('record-1'))->updatedAt()->toIso8601());
    }

    public function testTheLimitIsCappedByThePolicy(): void
    {
        $world = new DeclarationWorld((new WorkflowPolicy())->withPageSizes(1, 2));
        foreach ([1, 2, 3] as $number) {
            $world->recordWith('record-' . $number, DeclarationState::InvalidCredentials, 1, RecordSamples::childminder(), 'ref-' . $number, 'tenant-1', 'Y1869005780009');
        }

        static::assertSame(1, $this->requeue($world, null)->declarations);
        static::assertSame(2, $this->requeue($world, 10)->declarations);
    }

    public function testNothingToRequeueStillReportsZero(): void
    {
        static::assertSame(0, $this->requeue(new DeclarationWorld(), 5)->declarations);
    }

    private function requeue(DeclarationWorld $world, ?int $limit): RequeueReport
    {
        return (new RequeueAfterInvalidCredentialsHandler($world->records, $world->clock, $world->unitOfWork, $world->policy))(
            new RequeueAfterInvalidCredentials(DeclarationWorld::tenant(), $limit),
        );
    }
}
