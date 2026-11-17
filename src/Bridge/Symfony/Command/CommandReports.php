<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\OutputInterface;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\BatchItem;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\BatchReport;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\ItemOutcome;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Exception\DeclarationRecordingFailed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Exception\MandateRecordingFailed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Exception\RetryableUrssafFailure;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\UrssafException;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Validation\Violation;

/**
 * Execution guard and output of the console commands. Nothing personal is printed (ADR 0007): identifiers, codes,
 * states, amounts and instants only; the descriptions and messages of URSSAF are never printed.
 */
final class CommandReports
{
    /** Outcomes that make a batch command exit with a failure status. */
    private const FAILURES = [ItemOutcome::Failed, ItemOutcome::InvalidCredentials, ItemOutcome::ManualReview];

    /**
     * Runs a command body. An invalid usage (option, input refused by the domain) prints its errors and gives
     * Command::INVALID (2); any other package failure prints its message and gives Command::FAILURE (1).
     *
     * @param callable(): int $action
     * @param-immediately-invoked-callable $action
     */
    public static function guard(OutputInterface $output, callable $action): int
    {
        try {
            return $action();
        } catch (InvalidOptionException $failure) {
            $output->writeln(\sprintf('<error>%s</error>', $failure->getMessage()));

            return Command::INVALID;
        } catch (ValidationFailed $failure) {
            $errors = $failure->result()->errors();
            array_walk($errors, static fn(Violation $error) => $output->writeln(\sprintf('<error>%s: %s (%s)</error>', $error->path(), $error->message(), $error->code())));
            $output->writeln(\sprintf('<error>Invalid input: %d error(s).</error>', \count($errors)));

            return Command::INVALID;
        } catch (InvalidValue $failure) {
            $output->writeln(\sprintf('<error>%s (%s)</error>', $failure->getMessage(), $failure->violationCode()));

            return Command::INVALID;
        } catch (MandateRecordingFailed $failure) {
            $output->writeln(\sprintf('<error>%s</error>', $failure->getMessage()));
            $output->writeln(\sprintf('<comment>URSSAF accepted the request: do not run the command again. %s</comment>', self::mandateRecovery($failure)));

            return Command::FAILURE;
        } catch (DeclarationRecordingFailed $failure) {
            $output->writeln(\sprintf('<error>%s</error>', $failure->getMessage()));
            $documentReference = $failure->documentReference();
            if ($documentReference !== null) {
                $output->writeln(\sprintf('Document reference: %s', $documentReference));
            }
            $output->writeln('<comment>URSSAF accepted the declaration: never send it again. Run urssaf:paje:declarations:reconcile-stale, then urssaf:paje:declaration:resolve --declared-as=<document reference>.</comment>');

            return Command::FAILURE;
        } catch (RetryableUrssafFailure $failure) {
            $output->writeln(\sprintf('<error>%s</error>', $failure->getMessage()));
            $retryAfter = $failure->retryAfterSeconds();
            $output->writeln(\sprintf('<comment>Nothing was recorded as sent: run the command again%s.</comment>', $retryAfter === null ? ' later' : \sprintf(' in %d second(s) or more', $retryAfter)));

            return Command::FAILURE;
        } catch (UrssafException $failure) {
            $output->writeln(\sprintf('<error>%s</error>', $failure->getMessage()));

            return Command::FAILURE;
        }
    }

    private static function mandateRecovery(MandateRecordingFailed $failure): string
    {
        return $failure->params()['operation'] === Operation::CancelMandate->value
            ? 'Record the cancellation without any call: urssaf:paje:mandate:record-cancellation (same employer).'
            : 'Record the mandate without any call: urssaf:paje:mandate:import (same employer).';
    }

    /**
     * Prints the items of a report as a table followed by the count of each outcome.
     */
    public static function renderReport(OutputInterface $output, BatchReport $report): int
    {
        $table = new Table($output);
        $table->setHeaders(['Record', 'Outcome', 'Codes']);
        $table->setRows(array_map(
            static fn(BatchItem $item): array => [$item->subject, $item->outcome->value, implode(', ', $item->codes)],
            $report->items(),
        ));
        $table->render();

        $counts = array_map(
            static fn(ItemOutcome $outcome): string => \sprintf('%s: %d', $outcome->value, $report->count($outcome)),
            array_filter(ItemOutcome::cases(), static fn(ItemOutcome $outcome): bool => $report->count($outcome) > 0),
        );
        $output->writeln(\sprintf('%d record(s) processed%s.', $report->total(), $counts === [] ? '' : ' (' . implode(', ', $counts) . ')'));

        $failed = array_filter(self::FAILURES, static fn(ItemOutcome $outcome): bool => $report->count($outcome) > 0);

        return $failed === [] ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * Tells that the same operation is already running (its lock is held): nothing is done, which is not a failure.
     */
    public static function renderBusy(OutputInterface $output, string $resource): int
    {
        $output->writeln(\sprintf('<comment>Already running (lock "%s" is held): skipped.</comment>', $resource));

        return Command::SUCCESS;
    }

    /**
     * Prints what a dry run would process.
     *
     * @param list<array{string, string}> $rows tenant id and record identifier
     */
    public static function renderPlan(OutputInterface $output, array $rows): int
    {
        $table = new Table($output);
        $table->setHeaders(['Tenant', 'Record']);
        $table->setRows($rows);
        $table->render();
        $output->writeln(\sprintf('Dry run: %d record(s) would be processed, URSSAF was not called.', \count($rows)));

        return Command::SUCCESS;
    }

    /**
     * Prints a view as a two-column table.
     *
     * @param list<array{string, string}> $rows field and value
     */
    public static function renderFields(OutputInterface $output, array $rows): void
    {
        $table = new Table($output);
        $table->setHeaders(['Field', 'Value']);
        $table->setRows($rows);
        $table->render();
    }

    public static function yesNo(?bool $value): string
    {
        return match ($value) {
            true => 'yes',
            false => 'no',
            null => '-',
        };
    }

    public static function orDash(?string $value): string
    {
        return $value ?? '-';
    }
}
