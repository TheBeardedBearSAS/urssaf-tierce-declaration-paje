<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Tests\Unit\Application\Fake;

use Closure;
use LogicException;
use Override;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\SalaryDeclarer;
use TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port\Urssaf\SalaryPreDeclarer;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\ChildminderDeclaration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationResult;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\HomeCareDeclaration;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\PreDeclarationResult;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Employer\EmployerReference;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\TenantId;
use Throwable;

/**
 * Hand-written fake of the declaration gateway ports (045, 046, 050, 051): each operation answers what the test queued
 * (a result, or a throwable that is thrown), in order, and journals its call ("call:050"). An unexpected call fails the
 * test.
 */
final class FakeSalaryGateway implements SalaryPreDeclarer, SalaryDeclarer
{
    /** @var array<string, list<DeclarationResult|PreDeclarationResult|Throwable>> */
    private array $queues = [];

    /** @var list<string> */
    private array $calls = [];

    /** @var list<EmployerReference> */
    private array $employers = [];

    /** @var list<TenantId> */
    private array $tenants = [];

    /** @var (Closure(): void)|null another process writing while the next call is in flight */
    private ?Closure $intrusion = null;

    public function __construct(private readonly Journal $journal) {}

    /**
     * Runs $intrusion once, during the next call (after it was journaled, before it answers): another process changing
     * the stored records while the HTTP call is in flight.
     *
     * @param Closure(): void $intrusion
     */
    public function duringNextCall(Closure $intrusion): void
    {
        $this->intrusion = $intrusion;
    }

    /**
     * @param '045'|'046'|'050'|'051' $operation
     */
    public function queue(string $operation, DeclarationResult|PreDeclarationResult|Throwable ...$outcomes): void
    {
        $this->queues[$operation] = [...($this->queues[$operation] ?? []), ...array_values($outcomes)];
    }

    /**
     * @return list<string> the operations called, in order
     */
    public function calls(): array
    {
        return $this->calls;
    }

    /**
     * @return list<EmployerReference>
     */
    public function employers(): array
    {
        return $this->employers;
    }

    /**
     * @return list<TenantId>
     */
    public function tenants(): array
    {
        return $this->tenants;
    }

    #[Override]
    public function preDeclareChildminder(TenantId $tenantId, EmployerReference $employer, ChildminderDeclaration $declaration): PreDeclarationResult
    {
        return $this->preDeclaration('045', $tenantId, $employer);
    }

    #[Override]
    public function preDeclareHomeCare(TenantId $tenantId, EmployerReference $employer, HomeCareDeclaration $declaration): PreDeclarationResult
    {
        return $this->preDeclaration('046', $tenantId, $employer);
    }

    #[Override]
    public function declareChildminder(TenantId $tenantId, EmployerReference $employer, ChildminderDeclaration $declaration): DeclarationResult
    {
        return $this->declaration('050', $tenantId, $employer);
    }

    #[Override]
    public function declareHomeCare(TenantId $tenantId, EmployerReference $employer, HomeCareDeclaration $declaration): DeclarationResult
    {
        return $this->declaration('051', $tenantId, $employer);
    }

    private function preDeclaration(string $operation, TenantId $tenantId, EmployerReference $employer): PreDeclarationResult
    {
        $outcome = $this->answer($operation, $tenantId, $employer);

        return $outcome instanceof PreDeclarationResult ? $outcome : throw new LogicException('Queued a declaration result for ' . $operation . '.');
    }

    private function declaration(string $operation, TenantId $tenantId, EmployerReference $employer): DeclarationResult
    {
        $outcome = $this->answer($operation, $tenantId, $employer);

        return $outcome instanceof DeclarationResult ? $outcome : throw new LogicException('Queued a pre-declaration result for ' . $operation . '.');
    }

    private function answer(string $operation, TenantId $tenantId, EmployerReference $employer): DeclarationResult|PreDeclarationResult
    {
        $this->calls[] = $operation;
        $this->tenants[] = $tenantId;
        $this->employers[] = $employer;
        $this->journal->add('call:' . $operation);
        if ($this->intrusion instanceof Closure) {
            $intrusion = $this->intrusion;
            $this->intrusion = null;
            $intrusion();
        }
        $outcome = array_shift($this->queues[$operation]) ?? throw new LogicException('Unexpected call of ' . $operation . '.');
        if ($outcome instanceof Throwable) {
            throw $outcome;
        }

        return $outcome;
    }
}
