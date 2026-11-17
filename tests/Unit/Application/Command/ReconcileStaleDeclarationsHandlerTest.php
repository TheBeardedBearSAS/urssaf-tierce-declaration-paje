<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Command;

use DateInterval;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\ReconcileStaleDeclarations;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\ReconcileStaleDeclarationsHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\BatchItem;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\BatchReport;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\ItemOutcome;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\OutcomeUnknownReason;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Fake\DeclarationWorld;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Domain\Declaration\RecordSamples;

final class ReconcileStaleDeclarationsHandlerTest extends TestCase
{
    public function testAStaleSubmissionBecomesAnOutcomeUnknownForManualReview(): void
    {
        $world = new DeclarationWorld();
        $world->record('record-1', DeclarationState::Submitting);

        $report = $this->reconcile($world, null);

        static::assertSame(1, $report->total());
        static::assertSame(1, $report->count(ItemOutcome::ManualReview));
        static::assertEquals([new BatchItem('record-1', ItemOutcome::ManualReview, ['stale_submission'])], $report->items());
        $record = $world->records->get(DeclarationRecordId::fromString('record-1'));
        static::assertSame(DeclarationState::OutcomeUnknown, $record->state());
        static::assertSame(OutcomeUnknownReason::StaleSubmission, $record->outcomeUnknownReason());
        static::assertSame([], $record->errors());
        static::assertSame(DeclarationWorld::NOW, $record->updatedAt()->toIso8601());
        static::assertSame([], $world->gateway->calls());
        static::assertSame(
            ['begin', 'save:record-1', 'append:urssaf.paje.declaration.outcome_unknown', 'commit', 'publish:DeclarationOutcomeUnknown'],
            $world->journal->entries(),
        );
    }

    public function testEachRecordHasATransactionOfItsOwnEveryTenantIsCovered(): void
    {
        $world = new DeclarationWorld();
        $world->recordWith('record-1', DeclarationState::Submitting, 1, RecordSamples::childminder(), 'ref-1', 'tenant-1', 'Y1869005780009');
        $world->recordWith('record-2', DeclarationState::Submitting, 1, RecordSamples::childminder(), 'ref-2', 'tenant-2', 'Y1869005780009');

        $report = $this->reconcile($world, null);

        static::assertSame(['record-1', 'record-2'], array_map(static fn(BatchItem $item): string => $item->subject, $report->items()));
        static::assertSame(2, $world->transactions->committed());
        static::assertSame(
            ['begin', 'save:record-1', 'append:urssaf.paje.declaration.outcome_unknown', 'commit', 'publish:DeclarationOutcomeUnknown'],
            \array_slice($world->journal->entries(), 0, 5),
        );
    }

    public function testTheStaleDelayBoundaryIsInclusive(): void
    {
        $exactly = new DeclarationWorld((new WorkflowPolicy())->withStaleSubmissionAfter(new DateInterval('PT1H')));
        $exactly->record('record-1', DeclarationState::Submitting);
        $notYet = new DeclarationWorld((new WorkflowPolicy())->withStaleSubmissionAfter(new DateInterval('PT61M')));
        $notYet->record('record-1', DeclarationState::Submitting);

        static::assertSame(1, $this->reconcile($exactly, null)->total());
        static::assertSame(0, $this->reconcile($notYet, null)->total());
        static::assertSame(DeclarationState::Submitting, $notYet->records->get(DeclarationRecordId::fromString('record-1'))->state());
    }

    public function testOtherStatesAreIgnored(): void
    {
        $world = new DeclarationWorld();
        foreach (DeclarationState::cases() as $index => $state) {
            if ($state === DeclarationState::Submitting) {
                continue;
            }

            $world->recordWith('record-' . $index, $state, 1, RecordSamples::childminder(), 'ref-' . $index, 'tenant-1', 'Y1869005780009');
        }

        static::assertSame(0, $this->reconcile($world, null)->total());
        static::assertSame([], $world->journal->entries());
    }

    public function testTheLimitIsCappedByThePolicy(): void
    {
        $world = new DeclarationWorld((new WorkflowPolicy())->withPageSizes(1, 2));
        foreach ([1, 2, 3, 4] as $number) {
            $world->recordWith('record-' . $number, DeclarationState::Submitting, 1, RecordSamples::childminder(), 'ref-' . $number, 'tenant-1', 'Y1869005780009');
        }

        static::assertSame(1, $this->reconcile($world, null)->total());
        static::assertSame(2, $this->reconcile($world, 10)->total());
        static::assertSame(1, $this->reconcile($world, 1)->total());
    }

    private function reconcile(DeclarationWorld $world, ?int $limit): BatchReport
    {
        return (new ReconcileStaleDeclarationsHandler($world->records, $world->clock, $world->unitOfWork, $world->policy))(
            new ReconcileStaleDeclarations($limit),
        );
    }
}
