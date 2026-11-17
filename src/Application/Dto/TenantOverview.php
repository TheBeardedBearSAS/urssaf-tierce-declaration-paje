<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto;

/**
 * Records of one tenant by state. Every state is listed, in workflow order, with 0 when there is no record (ADR 0003).
 */
final readonly class TenantOverview
{
    /**
     * @param array<string, int> $employers    employer accounts by EmployerStage value
     * @param array<string, int> $mandates     mandates by MandateState value
     * @param array<string, int> $declarations declarations by DeclarationState value
     */
    public function __construct(
        public string $tenantId,
        public array $employers,
        public array $mandates,
        public array $declarations,
    ) {}

    /**
     * Declarations that wait for a manual action: outcome unknown, in error, with invalid credentials or already declared
     * at URSSAF (ADR 0003). Employers and mandates have no failure state.
     */
    public function needingAttention(): int
    {
        return $this->declarations['outcome_unknown'] + $this->declarations['error'] + $this->declarations['invalid_credentials'] + $this->declarations['already_declared'];
    }

    /**
     * @return array{tenant_id: string, employers: array<string, int>, mandates: array<string, int>, declarations: array<string, int>, needing_attention: int}
     */
    public function toArray(): array
    {
        return [
            'tenant_id' => $this->tenantId,
            'employers' => $this->employers,
            'mandates' => $this->mandates,
            'declarations' => $this->declarations,
            'needing_attention' => $this->needingAttention(),
        ];
    }
}
