<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Port;

/**
 * The twelve operations of the API Tierce Déclaration PAJE (api-reference §2). The backing value is the official method
 * number. They count towards a quota that URSSAF does not publish (ADR 0011): there is no per-operation limit.
 */
enum Operation: string
{
    /** 020 verify an employer account */
    case VerifyEmployer = '020';

    /** 021 verify an employee account */
    case VerifyEmployee = '021';

    /** 022 verify the entitlement of children */
    case VerifyChildren = '022';

    /** 023 associate an employee to an employer (may create the employee) */
    case AssociateEmployee = '023';

    /** 030 register a mandate */
    case RegisterMandate = '030';

    /** 031 cancel a mandate */
    case CancelMandate = '031';

    /** 040 estimate the contributions of a childminder employment */
    case EstimateChildminder = '040';

    /** 041 estimate the contributions of a home care employment */
    case EstimateHomeCare = '041';

    /** 045 pre-declare a childminder salary */
    case PreDeclareChildminder = '045';

    /** 046 pre-declare a home care salary */
    case PreDeclareHomeCare = '046';

    /** 050 declare a childminder salary */
    case DeclareChildminder = '050';

    /** 051 declare a home care salary */
    case DeclareHomeCare = '051';

    /**
     * Retry class of the operation (ADR 0003). A 429 or a local rate-limit refusal is retryable for every class: the
     * call provably did not reach URSSAF.
     */
    public function retryClass(): OperationRetryClass
    {
        return match ($this) {
            self::VerifyEmployer,
            self::VerifyEmployee,
            self::VerifyChildren,
            self::EstimateChildminder,
            self::EstimateHomeCare,
            self::PreDeclareChildminder,
            self::PreDeclareHomeCare => OperationRetryClass::Safe,
            self::DeclareChildminder, self::DeclareHomeCare => OperationRetryClass::NeverResent,
            self::AssociateEmployee, self::RegisterMandate, self::CancelMandate => OperationRetryClass::UnspecifiedWrite,
        };
    }

    /**
     * Short English name for logs and operator messages (no personal data).
     */
    public function label(): string
    {
        return match ($this) {
            self::VerifyEmployer => 'verify employer',
            self::VerifyEmployee => 'verify employee',
            self::VerifyChildren => 'verify children',
            self::AssociateEmployee => 'associate employee',
            self::RegisterMandate => 'register mandate',
            self::CancelMandate => 'cancel mandate',
            self::EstimateChildminder => 'estimate childminder contributions',
            self::EstimateHomeCare => 'estimate home care contributions',
            self::PreDeclareChildminder => 'pre-declare childminder salary',
            self::PreDeclareHomeCare => 'pre-declare home care salary',
            self::DeclareChildminder => 'declare childminder salary',
            self::DeclareHomeCare => 'declare home care salary',
        };
    }
}
