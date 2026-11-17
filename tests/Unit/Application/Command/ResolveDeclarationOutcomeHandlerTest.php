<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Command;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\ResolveDeclarationOutcome;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Command\ResolveDeclarationOutcomeHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\DeclarationSelector;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\DeclarationView;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvalidStateTransition;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Fake\DeclarationWorld;

final class ResolveDeclarationOutcomeHandlerTest extends TestCase
{
    public function testAnOutcomeFoundOnPajemploiIsRecordedAsDeclared(): void
    {
        $world = new DeclarationWorld();
        $world->record('record-1', DeclarationState::OutcomeUnknown);

        $view = $this->resolve($world, ResolveDeclarationOutcome::asDeclared(DeclarationWorld::tenant(), self::byId(), 'REF-77'));

        static::assertSame('declared', $view->status->state);
        static::assertSame('REF-77', $view->status->result?->documentReference);
        static::assertSame(DeclarationWorld::NOW, $view->status->updatedAt);
        $stored = $world->records->get(DeclarationRecordId::fromString('record-1'));
        static::assertSame(DeclarationState::Declared, $stored->state());
        static::assertSame(
            ['begin', 'save:record-1', 'append:urssaf.paje.declaration.declared', 'commit', 'publish:DeclarationDeclared'],
            $world->journal->entries(),
        );
        static::assertSame([], $world->gateway->calls());
    }

    public function testAnOutcomeNotReceivedPutsTheRecordBackToPending(): void
    {
        $world = new DeclarationWorld();
        $world->record('record-1', DeclarationState::OutcomeUnknown);

        $view = $this->resolve($world, ResolveDeclarationOutcome::asNotReceived(DeclarationWorld::tenant(), DeclarationSelector::byExternalReference(DeclarationWorld::reference())));

        static::assertSame('pending', $view->status->state);
        static::assertSame(
            ['begin', 'save:record-1', 'append:', 'commit', 'publish:DeclarationRequeued'],
            $world->journal->entries(),
        );
    }

    public function testARecordWhoseOutcomeIsKnownCannotBeResolved(): void
    {
        $world = new DeclarationWorld();
        $world->record('record-1', DeclarationState::Declared);

        $this->expectException(InvalidStateTransition::class);
        $this->resolve($world, ResolveDeclarationOutcome::asNotReceived(DeclarationWorld::tenant(), self::byId()));
    }

    public function testABlankReferenceIsRefusedAndNothingIsSaved(): void
    {
        $world = new DeclarationWorld();
        $world->record('record-1', DeclarationState::OutcomeUnknown);

        try {
            $this->resolve($world, ResolveDeclarationOutcome::asDeclared(DeclarationWorld::tenant(), self::byId(), '  '));
            static::fail('Expected an InvalidValue.');
        } catch (InvalidValue $invalid) {
            static::assertSame('document_reference.empty', $invalid->violationCode());
        }

        static::assertSame(DeclarationState::OutcomeUnknown, $world->records->get(DeclarationRecordId::fromString('record-1'))->state());
        static::assertSame(['begin', 'rollback'], $world->journal->entries());
    }

    public function testAnUnknownRecordOrOneOfAnotherTenantIsNotFound(): void
    {
        $world = new DeclarationWorld();
        $world->record('record-1', DeclarationState::OutcomeUnknown);

        $this->expectException(RecordNotFound::class);
        $this->resolve($world, ResolveDeclarationOutcome::asNotReceived(DeclarationWorld::tenant('tenant-2'), self::byId()));
    }

    public function testTheCommandKeepsWhatWasSaid(): void
    {
        $declared = ResolveDeclarationOutcome::asDeclared(DeclarationWorld::tenant(), self::byId(), 'REF');
        $notReceived = ResolveDeclarationOutcome::asNotReceived(DeclarationWorld::tenant(), self::byId());

        static::assertSame('REF', $declared->documentReference);
        static::assertNull($notReceived->documentReference);
    }

    private static function byId(): DeclarationSelector
    {
        return DeclarationSelector::byRecordId(DeclarationRecordId::fromString('record-1'));
    }

    private function resolve(DeclarationWorld $world, ResolveDeclarationOutcome $command): DeclarationView
    {
        return (new ResolveDeclarationOutcomeHandler($world->locator, $world->records, $world->clock, $world->unitOfWork))($command);
    }
}
