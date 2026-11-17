<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Command;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Lock\OperationLocks;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Support\HeldLocks;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Support\HttpScript;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Support\Output;
use TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Functional\Symfony\Support\Scenario;

/**
 * @mago-expect lint:too-many-methods
 */
final class DeclarationFollowUpCommandsTest extends TestCase
{
    private const RECONCILE = 'urssaf:paje:declarations:reconcile-stale';
    private const RESOLVE = 'urssaf:paje:declaration:resolve';
    private const REQUEUE = 'urssaf:paje:declarations:requeue-invalid-credentials';
    private const SHOW = 'urssaf:paje:declaration:show';
    private const LIST = 'urssaf:paje:declarations:list';

    public function testAStaleSubmissionIsMovedToUnknownAndRaisesAnAlert(): void
    {
        $harness = Harness::boot();
        $id = Scenario::staleDeclaration($harness);
        $command = $harness->command(self::RECONCILE);

        static::assertSame(1, $command->execute([]));

        static::assertContains([$id->value(), 'manual_review', 'stale_submission'], Output::rows($command->getDisplay()));
        static::assertStringContainsString('1 record(s) processed (manual_review: 1).', $command->getDisplay());
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_050));
    }

    public function testTheOldNameStillWorks(): void
    {
        $harness = Harness::boot();
        $command = $harness->command('urssaf:paje:declarations:reconcile');

        static::assertSame(0, $command->execute([]));

        static::assertStringContainsString('0 record(s) processed.', $command->getDisplay());
    }

    public function testNothingStaleIsASuccess(): void
    {
        $harness = Harness::boot();
        Scenario::declaration($harness);
        $command = $harness->command(self::RECONCILE);

        static::assertSame(0, $command->execute([]));

        static::assertStringContainsString('0 record(s) processed.', $command->getDisplay());
    }

    public function testTheDryRunListsTheStaleSubmissionsAndChangesNothing(): void
    {
        $harness = Harness::boot();
        $id = Scenario::staleDeclaration($harness);
        $command = $harness->command(self::RECONCILE);

        static::assertSame(0, $command->execute(['--dry-run' => true]));

        static::assertContains([Scenario::TENANT, $id->value()], Output::rows($command->getDisplay()));
        static::assertStringContainsString('Dry run: 1 record(s) would be processed, URSSAF was not called.', $command->getDisplay());
        $show = $harness->command(self::SHOW);
        static::assertSame(0, $show->execute(['--record-id' => $id->value()]));
        static::assertSame('submitting', Fields::of($show->getDisplay())['State']);
    }

    public function testTheDryRunIgnoresARecentSubmission(): void
    {
        $harness = Harness::boot();
        Scenario::staleDeclaration($harness);
        $harness->clock->modify('-10 minutes');
        $command = $harness->command(self::RECONCILE);

        static::assertSame(0, $command->execute(['--dry-run' => true]));

        static::assertStringContainsString('Dry run: 0 record(s)', $command->getDisplay());
    }

    public function testTheLimitCapsTheDryRun(): void
    {
        $harness = Harness::boot();
        Scenario::staleDeclaration($harness, Scenario::TENANT, 'declaration-1');
        Scenario::staleDeclaration($harness, Scenario::TENANT, 'declaration-2');
        $command = $harness->command(self::RECONCILE);

        static::assertSame(0, $command->execute(['--dry-run' => true, '--limit' => '1']));

        static::assertStringContainsString('Dry run: 1 record(s)', $command->getDisplay());
    }

    public function testAnInvalidLimitExitsWithTwo(): void
    {
        $command = Harness::boot()->command(self::RECONCILE);

        static::assertSame(2, $command->execute(['--limit' => '0']));

        static::assertStringContainsString('The --limit option must be a positive integer.', $command->getDisplay());
    }

    public function testARunningReconciliationIsSkipped(): void
    {
        $harness = Harness::boot([], false, true);
        $id = Scenario::staleDeclaration($harness);
        $held = HeldLocks::hold($harness, OperationLocks::resource('declarations.reconcile-stale', '*'));
        $command = $harness->command(self::RECONCILE);

        static::assertSame(0, $command->execute([]));

        static::assertStringContainsString('Already running (lock "urssaf_paje.declarations.reconcile-stale.*" is held): skipped.', $command->getDisplay());
        $held->release();
        $show = $harness->command(self::SHOW);
        $show->execute(['--record-id' => $id->value()]);
        static::assertSame('submitting', Fields::of($show->getDisplay())['State']);
    }

    public function testAnUnknownOutcomeIsResolvedAsDeclared(): void
    {
        $harness = Harness::boot();
        $unknown = Scenario::outcomeUnknownDeclaration($harness);
        $command = $harness->command(self::RESOLVE);

        static::assertSame(0, $command->execute(['--record-id' => $unknown->declaration->recordId, '--declared-as' => 'DOC-1234']));

        $fields = Fields::of($command->getDisplay());
        static::assertSame('declared', $fields['State']);
        static::assertSame('DOC-1234', $fields['Document reference']);
        static::assertStringNotContainsString('Declare it again', $command->getDisplay());
    }

    public function testAnUnknownOutcomeIsResolvedAsNotReceived(): void
    {
        $harness = Harness::boot();
        $unknown = Scenario::outcomeUnknownDeclaration($harness);
        $command = $harness->command(self::RESOLVE);

        static::assertSame(0, $command->execute(['--declaration' => Scenario::DECLARATION, '--not-received' => true]));

        static::assertSame('pending', Fields::of($command->getDisplay())['State']);
        static::assertStringContainsString('Declare it again with urssaf:paje:declare (same external reference and content).', $command->getDisplay());
        static::assertSame($unknown->declaration->recordId, Fields::of($command->getDisplay())['Record id']);
    }

    /**
     * @param array<string, mixed> $input
     */
    #[DataProvider('invalidResolutions')]
    public function testAnInvalidResolutionExitsWithTwo(array $input, string $message): void
    {
        $harness = Harness::boot();
        Scenario::outcomeUnknownDeclaration($harness);
        $command = $harness->command(self::RESOLVE);

        static::assertSame(2, $command->execute($input));

        static::assertStringContainsString($message, $command->getDisplay());
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidResolutions(): iterable
    {
        yield 'no outcome' => [['--declaration' => Scenario::DECLARATION], 'Give one of --declared-as or --not-received.'];
        yield 'both outcomes' => [['--declaration' => Scenario::DECLARATION, '--declared-as' => 'DOC-1', '--not-received' => true], 'Give one of --declared-as or --not-received.'];
        yield 'no declaration' => [['--declared-as' => 'DOC-1'], 'Give one of --declaration or --record-id.'];
        yield 'two declarations' => [['--declaration' => Scenario::DECLARATION, '--record-id' => 'r', '--declared-as' => 'DOC-1'], 'Give one of --declaration or --record-id.'];
        yield 'blank reference' => [['--declaration' => Scenario::DECLARATION, '--declared-as' => ' '], 'document_reference'];
    }

    public function testADeclarationThatIsNotUnknownCannotBeResolved(): void
    {
        $harness = Harness::boot();
        Scenario::declaration($harness);
        $command = $harness->command(self::RESOLVE);

        static::assertSame(1, $command->execute(['--declaration' => Scenario::DECLARATION, '--not-received' => true]));
    }

    public function testAnUnknownDeclarationExitsWithOne(): void
    {
        $command = Harness::boot()->command(self::RESOLVE);

        static::assertSame(1, $command->execute(['--declaration' => 'unknown', '--not-received' => true]));
    }

    public function testTheInvalidCredentialsAreRequeuedWithoutAnyCall(): void
    {
        $harness = Harness::boot();
        Scenario::invalidCredentialsDeclaration($harness);
        $calls = $harness->urssaf->calls(HttpScript::ROUTE_050);
        $command = $harness->command(self::REQUEUE);

        static::assertSame(0, $command->execute([]));

        static::assertSame([['Records', 'Requeued'], ['declarations', '1']], Output::rows($command->getDisplay()));
        static::assertStringContainsString('The requeued declarations are pending: send them again with urssaf:paje:declare.', $command->getDisplay());
        static::assertSame($calls, $harness->urssaf->calls(HttpScript::ROUTE_050));
        $show = $harness->command(self::SHOW);
        $show->execute(['--declaration' => Scenario::DECLARATION]);
        static::assertSame('pending', Fields::of($show->getDisplay())['State']);
    }

    public function testTheRequeueDryRunListsWithoutChangingAnything(): void
    {
        $harness = Harness::boot();
        $submission = Scenario::invalidCredentialsDeclaration($harness);
        $command = $harness->command(self::REQUEUE);

        static::assertSame(0, $command->execute(['--dry-run' => true, '--limit' => '5']));

        static::assertContains([Scenario::TENANT, $submission->declaration->recordId], Output::rows($command->getDisplay()));
        static::assertStringContainsString('Dry run: 1 record(s) would be processed', $command->getDisplay());
        $show = $harness->command(self::SHOW);
        $show->execute(['--declaration' => Scenario::DECLARATION]);
        static::assertSame('invalid_credentials', Fields::of($show->getDisplay())['State']);
    }

    public function testTheRequeueNeedsATenantWhenSeveralAreConfigured(): void
    {
        $command = Harness::boot(Accounts::withSecondTenant())->command(self::REQUEUE);

        static::assertSame(2, $command->execute([]));
    }

    public function testARunningRequeueIsSkipped(): void
    {
        $harness = Harness::boot([], false, true);
        Scenario::invalidCredentialsDeclaration($harness);
        $held = HeldLocks::hold($harness, OperationLocks::resource('requeue-invalid-credentials', Scenario::TENANT));
        $command = $harness->command(self::REQUEUE);

        static::assertSame(0, $command->execute([]));

        static::assertStringContainsString('Already running (lock "urssaf_paje.requeue-invalid-credentials.tenant-1" is held): skipped.', $command->getDisplay());
        $held->release();
    }

    public function testADeclarationIsShown(): void
    {
        $harness = Harness::boot();
        $submission = Scenario::declaration($harness);
        $command = $harness->command(self::SHOW);

        static::assertSame(0, $command->execute(['--declaration' => Scenario::DECLARATION]));

        $fields = Fields::of($command->getDisplay());
        static::assertSame($submission->declaration->recordId, $fields['Record id']);
        static::assertSame('declared', $fields['State']);
        static::assertSame('-', $fields['Unknown outcome reason']);
        static::assertSame('-', $fields['Error codes']);
        static::assertSame('1', $fields['Attempts']);
        static::assertSame('ER_API_020_NB_0000', $fields['Non-blocking codes']);
        static::assertSame('2026-11-15T10:00:00Z', $fields['Updated at']);
    }

    public function testAnUnknownOutcomeIsShownWithItsReason(): void
    {
        $harness = Harness::boot();
        Scenario::outcomeUnknownDeclaration($harness);
        $command = $harness->command(self::SHOW);

        static::assertSame(0, $command->execute(['--declaration' => Scenario::DECLARATION]));

        $fields = Fields::of($command->getDisplay());
        static::assertSame('outcome_unknown', $fields['State']);
        static::assertSame('may_have_reached_urssaf', $fields['Unknown outcome reason']);
        static::assertSame('-', $fields['Error codes']);
    }

    public function testAnUnknownDeclarationIsNotShown(): void
    {
        $command = Harness::boot()->command(self::SHOW);

        static::assertSame(1, $command->execute(['--declaration' => 'unknown']));
    }

    public function testAnInvalidSelectorIsNotShown(): void
    {
        $command = Harness::boot()->command(self::SHOW);

        static::assertSame(2, $command->execute([]));
    }

    public function testTheDeclarationsOfAStateAreListed(): void
    {
        $harness = Harness::boot();
        $submission = Scenario::outcomeUnknownDeclaration($harness);
        $command = $harness->command(self::LIST);

        static::assertSame(0, $command->execute(['--state' => 'outcome_unknown']));

        static::assertSame([['Record id', 'External reference', 'State', 'Attempts', 'Updated at'], [$submission->declaration->recordId, Scenario::DECLARATION, 'outcome_unknown', '1', '2026-11-15T10:00:00Z']], Output::rows($command->getDisplay()));
        static::assertStringContainsString('1 declaration(s).', $command->getDisplay());
    }

    public function testTheListIsCappedByTheLimit(): void
    {
        $harness = Harness::boot();
        Scenario::staleDeclaration($harness, Scenario::TENANT, 'declaration-1');
        Scenario::staleDeclaration($harness, Scenario::TENANT, 'declaration-2');
        $command = $harness->command(self::LIST);

        static::assertSame(0, $command->execute(['--state' => 'submitting', '--limit' => '1']));

        static::assertStringContainsString('1 declaration(s).', $command->getDisplay());
    }

    public function testDeclarationsAreListedByReferenceAndByRecordId(): void
    {
        $harness = Harness::boot();
        $submission = Scenario::declaration($harness);
        $byReference = $harness->command(self::LIST);
        $byRecord = $harness->command(self::LIST);

        static::assertSame(0, $byReference->execute(['--declaration' => [Scenario::DECLARATION, 'unknown']]));
        static::assertSame(0, $byRecord->execute(['--record-id' => [$submission->declaration->recordId, 'unknown']]));

        static::assertStringContainsString('1 declaration(s).', $byReference->getDisplay());
        static::assertStringContainsString('1 declaration(s).', $byRecord->getDisplay());
    }

    /**
     * @param array<string, mixed> $input
     */
    #[DataProvider('invalidLists')]
    public function testAnInvalidListExitsWithTwo(array $input, string $message): void
    {
        $command = Harness::boot()->command(self::LIST);

        static::assertSame(2, $command->execute($input));

        static::assertStringContainsString($message, $command->getDisplay());
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidLists(): iterable
    {
        yield 'nothing' => [[], 'Give exactly one of --state, --declaration or --record-id.'];
        yield 'state and reference' => [['--state' => 'pending', '--declaration' => ['a']], 'Give exactly one of --state, --declaration or --record-id.'];
        yield 'reference and record id' => [['--declaration' => ['a'], '--record-id' => ['b']], 'Give exactly one of --state, --declaration or --record-id.'];
        yield 'unknown state' => [['--state' => 'lost'], 'The --state option must be a declaration state, e.g. outcome_unknown.'];
        yield 'limit without state' => [['--declaration' => ['a'], '--limit' => '2'], '--limit only applies with --state.'];
        yield 'invalid limit' => [['--state' => 'pending', '--limit' => 'x'], 'The --limit option must be a positive integer.'];
    }
}
