<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Infrastructure\Urssaf\Exception;

/**
 * Error code catalogue of the URSSAF API (api-reference §3.6, PDF v1.5.2). Values are the exact wire codes.
 *
 * Unknown codes are legitimate (URSSAF may add some): use {@see self::fromCode()}, which returns null for them.
 * The `ER_API_DECLA_*` family is written with 4 or 5 digits in the PDF (api-reference §5): the enum holds the PDF
 * spellings and {@see self::fromCode()} also recognises the other digit count by numeric value (ADR 0005).
 *
 * One case per documented code, hence the size.
 *
 * @mago-expect lint:too-many-enum-cases
 */
enum ApiErrorCode: string
{
    // Generic and HTTP-level codes.
    case InvalidParameter = 'PARAM_INVALIDE';
    case BirthDateMismatch = 'ER_API_020_0000';
    case EmployerNotFound = 'ER_API_020_0010';
    case EmployerMissing = 'PE_INEXISTANT';
    case MandateMissing = 'MANDAT_INEXISTANT';
    case MandateHeldElsewhere = 'COMPTE_DEJA_MANDATE';
    case TechnicalError = 'ERR_TECHNIQUE';
    case HourlyRateTechnicalError = 'ERREUR_TECHNIQUE';

    // Blocking codes of the declaration operations (045, 046, 050, 051).
    case DeclarationTooEarly = 'ER_API_DECLA_0000';
    case DeclarationNoLongerAllowed = 'ER_API_DECLA_0001';
    case DeclarationLimitReached = 'ER_API_DECLA_0002';
    case HomeCareDeclarationExists = 'ER_API_DECLA_00020';
    case OneChildMaximum = 'ER_API_DECLA_00040';
    case EmploymentPeriodInvalid = 'ER_API_DECLA_00071';
    case EmploymentPeriodTooOld = 'ER_API_DECLA_00072';
    case EmployeeNotAssociated = 'ER_API_DECLA_00100';
    case EmployeeBirthDateMismatch = 'ER_API_021_0010';
    case NoActivityDays = 'ER_API_050_0010';
    case HourlyRateRequired = 'ER_API_050_0015';
    case ChildminderNetSalaryVeryHigh = 'ER_API_050_0020';
    case ChildminderNetSalaryInconsistent = 'ER_API_050_0021';
    case ChildminderNetSalaryTooLow = 'ER_API_050_0022';
    case HoursInconsistent = 'ER_API_050_0030';
    case ChildminderNormalHoursExceeded = 'ER_API_050_0031';
    case HomeCareNormalHoursExceeded = 'ER_API_051_0031';
    case ConsecutiveDaysExceeded = 'ER_API_050_0050';
    case HandicapDaysInvalid = 'ER_API_050_0051';
    case ContractEndChildMismatch = 'ER_API_050_0060';
    case ChildminderDeclarationExists = 'ER_API_050_0070';
    case ChildHoursExceeded = 'ER_API_050_0080';
    case HomeCareHourlyRateVeryHigh = 'ER_API_051_0000';
    case HomeCareNetSalaryVeryHigh = 'ER_API_051_0010';
    case OvertimeHoursInconsistent = 'ER_API_051_0020';
    case HomeCareHourlyRateBelowMinimumWage = 'ER_API_051_0040';

    // Non-blocking codes (`erreursNonBloquantes`, HTTP 200).
    case EmployerNotAuthorised = 'ER_API_020_NB_0000';
    case EmployerBankDetailsInvalid = 'ER_API_020_NB_0010';
    case EmployeeNotVerified = 'ER_API_021_NB_0000';
    case ChildUnknown = 'ER_API_022_NB_0000';
    case ChildNoLongerEligible = 'ER_API_041_NB_0010';
    case CalculationModeInconsistent = 'ER_API_041_NB_0020';
    case EmployeeAlreadyAssociated = 'ER_API_023_NB_0000';
    case AgreementYearInconsistentWithBirth = 'ER_API_023_DATE_AGREMENT_NB_0000';
    case AgreementEndDateNotInFuture = 'ER_API_023_DATE_AGREMENT_NB_0010';
    case AgreementDateIncorrect = 'ER_AG_NB_00000';
    case AgreementExpiring = 'ER_AG_NB_00010';
    case ChildminderHourlyRateCapped = 'ER_API_050_NB_0016';
    case HomeCareHourlyRateCapped = 'ER_API_051_NB_0016';
    case SalaryGapWithPreviousDeclarations = 'ER_API_DECLA_NB_0010';

    private const DECLA_FAMILY = '/^ER_API_DECLA_(\d{4,5})$/D';

    /**
     * Normalises a raw wire code: surrounding or inner whitespace is dropped (the official documentation contains
     * codes with trailing spaces and codes broken across lines).
     */
    public static function normalise(string $rawCode): string
    {
        return preg_replace('/\s+/', '', $rawCode) ?? $rawCode;
    }

    public static function fromCode(string $rawCode): ?self
    {
        $code = self::normalise($rawCode);
        $number = [];
        if (preg_match(self::DECLA_FAMILY, $code, $number) === 1) {
            return self::fromDeclaNumber((int) $number[1]);
        }

        return self::tryFrom($code);
    }

    /**
     * Non-blocking codes carry `_NB_` (api-reference §1.3).
     */
    public function isNonBlocking(): bool
    {
        return str_contains($this->value, '_NB_');
    }

    /**
     * Same `ER_API_DECLA_` number, whatever the digit count (`0020` is `00020`).
     */
    private static function fromDeclaNumber(int $number): ?self
    {
        foreach (self::cases() as $case) {
            $candidate = [];
            if (preg_match(self::DECLA_FAMILY, $case->value, $candidate) === 1 && (int) $candidate[1] === $number) {
                return $case;
            }
        }

        return null;
    }
}
