<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Command;

use Symfony\Component\Console\Output\OutputInterface;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto\EmployerSelector;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Query\GetEmployerAccount;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Query\GetEmployerAccountHandler;
use TheBeardedBear\UrssafTierceDeclarationPaje\Bridge\Symfony\Lock\OperationLocks;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;

/**
 * The lock of the commands that work on one employer (ADR 0006: the mandate commands of one employer are serialized).
 * Whatever the selector (external reference, Pajemploi number, account id), the lock is taken on the id of the account,
 * so that two selectors of the same employer exclude each other, and no personal identifier reaches a lock name.
 */
final readonly class CommandLocks
{
    /** The operation shared by every command that changes the mandate or the existence of an employer account. */
    public const MANDATE = 'mandate';

    public function __construct(private OperationLocks $locks, private GetEmployerAccountHandler $accounts) {}

    /**
     * Runs the action while holding the lock of the employer, or says that it is already running.
     *
     * @param callable(): int $action
     * @param-immediately-invoked-callable $action
     *
     * @throws RecordNotFound when the employer is not tracked
     */
    public function forEmployer(OutputInterface $output, TenantId $tenant, EmployerSelector $selector, string $operation, callable $action): int
    {
        $accountId = ($this->accounts)(new GetEmployerAccount($tenant, $selector))->accountId;

        return $this->locks->exclusively($operation, $accountId, $action, static fn(string $resource): int => CommandReports::renderBusy($output, $resource));
    }
}
