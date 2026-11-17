<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Policy;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\DeclarationRecord;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\CalendarDate;

/**
 * Configuration shared by the use case handlers (provided by the framework bridge). ADR 0003, ADR 0008, ADR 0009,
 * ADR 0012, ADR 0015. Immutable: the `withX` methods return a modified copy.
 *
 * - businessTimeZone: time zone of the business rules ("today", dates not in the future);
 * - staleSubmissionAfter: how long a submission may stay unanswered before being reconciled (outcome unknown);
 * - maxSubmissionAttempts: submission attempts after which a technical failure is not requeued automatically;
 * - requireLocalMandate: whether a declaration needs a local mandate (ADR 0009: the mandate guard, unless a command
 *   opts out; off by default);
 * - preDeclareBeforeDeclaring: whether DeclareSalary runs the 045 / 046 pre-declaration check first (ADR 0008; off by
 *   default);
 * - defaultPageSize / maxPageSize: number of records loaded by a batch use case when no / a larger limit is asked;
 * - allowErasureWithUnknownOutcomes / allowErasureWithActiveMandate: default of the two opt-in refusals of ForgetEmployer
 *   (a command states its own choice when it is not null; a submission in flight is always refused, ADR 0015);
 * - outboxRetention: how long a dispatched integration event stays in the outbox before it is purged (ADR 0012).
 *
 * @mago-expect lint:too-many-methods One accessor and one `withX` per tunable.
 */
final readonly class WorkflowPolicy
{
    public const DEFAULT_PAGE_SIZE = 100;
    public const MAX_PAGE_SIZE = 1000;
    public const DEFAULT_MAX_SUBMISSION_ATTEMPTS = DeclarationRecord::DEFAULT_MAX_ATTEMPTS;
    public const DEFAULT_STALE_SUBMISSION_AFTER = 'PT15M';
    public const DEFAULT_OUTBOX_RETENTION = 'P30D';

    /** @var positive-int */
    private int $maxSubmissionAttempts;

    /** @var positive-int */
    private int $defaultPageSize;

    /** @var positive-int */
    private int $maxPageSize;

    /**
     * @throws InvariantViolation "workflow_policy.invalid_max_submission_attempts", "workflow_policy.invalid_page_size"
     *                            or "workflow_policy.default_page_size_above_max"
     */
    public function __construct(
        private DateTimeZone $businessTimeZone = new DateTimeZone('Europe/Paris'),
        private DateInterval $staleSubmissionAfter = new DateInterval(self::DEFAULT_STALE_SUBMISSION_AFTER),
        int $maxSubmissionAttempts = self::DEFAULT_MAX_SUBMISSION_ATTEMPTS,
        private bool $requireLocalMandate = false,
        private bool $preDeclareBeforeDeclaring = false,
        int $defaultPageSize = self::DEFAULT_PAGE_SIZE,
        int $maxPageSize = self::MAX_PAGE_SIZE,
        private bool $allowErasureWithUnknownOutcomes = false,
        private bool $allowErasureWithActiveMandate = false,
        private DateInterval $outboxRetention = new DateInterval(self::DEFAULT_OUTBOX_RETENTION),
    ) {
        if ($maxPageSize < $defaultPageSize) {
            throw InvariantViolation::because(
                'workflow_policy.default_page_size_above_max',
                'The default page size must not be greater than the maximum page size.',
                ['default_page_size' => $defaultPageSize, 'max_page_size' => $maxPageSize],
            );
        }
        $this->defaultPageSize = self::positivePageSize($defaultPageSize);
        $this->maxPageSize = self::positivePageSize($maxPageSize);
        if ($maxSubmissionAttempts < 1) {
            throw InvariantViolation::because(
                'workflow_policy.invalid_max_submission_attempts',
                'The maximum number of submission attempts must be a positive integer.',
                ['max_submission_attempts' => $maxSubmissionAttempts],
            );
        }
        $this->maxSubmissionAttempts = $maxSubmissionAttempts;
    }

    public function businessTimeZone(): DateTimeZone
    {
        return $this->businessTimeZone;
    }

    public function staleSubmissionAfter(): DateInterval
    {
        return $this->staleSubmissionAfter;
    }

    /**
     * Submission attempts after which a technical failure is not requeued automatically any more (the declaration
     * stays in error until handled manually).
     *
     * @return positive-int
     */
    public function maxSubmissionAttempts(): int
    {
        return $this->maxSubmissionAttempts;
    }

    /**
     * ADR 0009: the mandate guard is off unless the host enables it (a command can still opt in or out).
     */
    public function requireLocalMandate(): bool
    {
        return $this->requireLocalMandate;
    }

    /**
     * ADR 0008: whether DeclareSalary pre-declares (045 / 046) before declaring (050 / 051).
     */
    public function preDeclareBeforeDeclaring(): bool
    {
        return $this->preDeclareBeforeDeclaring;
    }

    /**
     * ADR 0015: whether ForgetEmployer erases declarations whose outcome is unknown when the command does not decide.
     */
    public function allowErasureWithUnknownOutcomes(): bool
    {
        return $this->allowErasureWithUnknownOutcomes;
    }

    /**
     * ADR 0015: whether ForgetEmployer erases an employer with an active local mandate when the command does not decide.
     */
    public function allowErasureWithActiveMandate(): bool
    {
        return $this->allowErasureWithActiveMandate;
    }

    /**
     * ADR 0012: how long a dispatched integration event is kept in the outbox.
     */
    public function outboxRetention(): DateInterval
    {
        return $this->outboxRetention;
    }

    public function withBusinessTimeZone(DateTimeZone $businessTimeZone): self
    {
        return new self(...[...$this->arguments(), 'businessTimeZone' => $businessTimeZone]);
    }

    public function withStaleSubmissionAfter(DateInterval $staleSubmissionAfter): self
    {
        return new self(...[...$this->arguments(), 'staleSubmissionAfter' => $staleSubmissionAfter]);
    }

    /**
     * @throws InvariantViolation "workflow_policy.invalid_max_submission_attempts"
     */
    public function withMaxSubmissionAttempts(int $maxSubmissionAttempts): self
    {
        return new self(...[...$this->arguments(), 'maxSubmissionAttempts' => $maxSubmissionAttempts]);
    }

    public function withRequireLocalMandate(bool $requireLocalMandate): self
    {
        return new self(...[...$this->arguments(), 'requireLocalMandate' => $requireLocalMandate]);
    }

    public function withPreDeclareBeforeDeclaring(bool $preDeclareBeforeDeclaring): self
    {
        return new self(...[...$this->arguments(), 'preDeclareBeforeDeclaring' => $preDeclareBeforeDeclaring]);
    }

    /**
     * @throws InvariantViolation "workflow_policy.invalid_page_size" or "workflow_policy.default_page_size_above_max"
     */
    public function withPageSizes(int $defaultPageSize, int $maxPageSize): self
    {
        return new self(...[...$this->arguments(), 'defaultPageSize' => $defaultPageSize, 'maxPageSize' => $maxPageSize]);
    }

    public function withAllowErasureWithUnknownOutcomes(bool $allow): self
    {
        return new self(...[...$this->arguments(), 'allowErasureWithUnknownOutcomes' => $allow]);
    }

    public function withAllowErasureWithActiveMandate(bool $allow): self
    {
        return new self(...[...$this->arguments(), 'allowErasureWithActiveMandate' => $allow]);
    }

    public function withOutboxRetention(DateInterval $outboxRetention): self
    {
        return new self(...[...$this->arguments(), 'outboxRetention' => $outboxRetention]);
    }

    /**
     * The calendar date of the instant in the business time zone (ADR 0001, api-reference §4.1).
     */
    public function today(DateTimeImmutable $now): CalendarDate
    {
        return CalendarDate::fromDateTime($now, $this->businessTimeZone);
    }

    /**
     * Number of records to load: the requested limit (default page size when null), capped by the maximum page size.
     *
     * @return positive-int
     *
     * @throws InvariantViolation "workflow_policy.invalid_limit" for a limit lower than 1
     */
    public function pageSize(?int $requestedLimit): int
    {
        $limit = $requestedLimit ?? $this->defaultPageSize;
        if ($limit < 1) {
            throw InvariantViolation::because(
                'workflow_policy.invalid_limit',
                'The limit must be a positive integer.',
                ['limit' => $limit],
            );
        }

        return min($limit, $this->maxPageSize);
    }

    /**
     * @return array{
     *     businessTimeZone: DateTimeZone,
     *     staleSubmissionAfter: DateInterval,
     *     maxSubmissionAttempts: positive-int,
     *     requireLocalMandate: bool,
     *     preDeclareBeforeDeclaring: bool,
     *     defaultPageSize: positive-int,
     *     maxPageSize: positive-int,
     *     allowErasureWithUnknownOutcomes: bool,
     *     allowErasureWithActiveMandate: bool,
     *     outboxRetention: DateInterval,
     * }
     */
    private function arguments(): array
    {
        return [
            'businessTimeZone' => $this->businessTimeZone,
            'staleSubmissionAfter' => $this->staleSubmissionAfter,
            'maxSubmissionAttempts' => $this->maxSubmissionAttempts,
            'requireLocalMandate' => $this->requireLocalMandate,
            'preDeclareBeforeDeclaring' => $this->preDeclareBeforeDeclaring,
            'defaultPageSize' => $this->defaultPageSize,
            'maxPageSize' => $this->maxPageSize,
            'allowErasureWithUnknownOutcomes' => $this->allowErasureWithUnknownOutcomes,
            'allowErasureWithActiveMandate' => $this->allowErasureWithActiveMandate,
            'outboxRetention' => $this->outboxRetention,
        ];
    }

    /**
     * @return positive-int
     */
    private static function positivePageSize(int $pageSize): int
    {
        if ($pageSize < 1) {
            throw InvariantViolation::because(
                'workflow_policy.invalid_page_size',
                'Page sizes must be positive integers.',
                ['page_size' => $pageSize],
            );
        }

        return $pageSize;
    }
}
