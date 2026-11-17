<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Exception\InvalidValue;

/**
 * Error reported for a submission: URSSAF error (`code`, api-reference §1.3, with the `message` and `description` it
 * came with) or technical failure, and its `kind` (the value of the application's `RejectionKind`, ADR 0005, kept as
 * text because the domain depends on nothing). Code and kind are mandatory; message and description may be absent.
 *
 * The message and the description may echo host values (ADR 0007, URSSAF message templates are filled with them): both
 * are masked in debug dumps and never put in exception messages.
 */
final readonly class SubmissionError
{
    private const SUBJECT_CODE = 'submission_error_code';
    private const SUBJECT_KIND = 'submission_error_kind';

    private function __construct(
        private string $code,
        private string $kind,
        private ?string $message,
        private ?string $description,
    ) {}

    /**
     * @throws InvalidValue "submission_error_code.empty" or "submission_error_kind.empty"
     */
    public static function of(string $code, string $kind, #[SensitiveParameter] ?string $message, #[SensitiveParameter] ?string $description): self
    {
        if (trim($code) === '') {
            throw InvalidValue::empty(self::SUBJECT_CODE);
        }
        if (trim($kind) === '') {
            throw InvalidValue::empty(self::SUBJECT_KIND);
        }

        return new self($code, $kind, $message, $description);
    }

    public function code(): string
    {
        return $this->code;
    }

    /**
     * The value of the application's `RejectionKind` (e.g. "duplicate_declaration", "technical").
     */
    public function kind(): string
    {
        return $this->kind;
    }

    public function message(): ?string
    {
        return $this->message;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    /**
     * @return array{code: string, kind: string, message: ?string, description: ?string}
     */
    public function __debugInfo(): array
    {
        return [
            'code' => $this->code,
            'kind' => $this->kind,
            'message' => $this->message === null ? null : '***',
            'description' => $this->description === null ? null : '***',
        ];
    }
}
