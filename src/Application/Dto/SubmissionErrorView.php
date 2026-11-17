<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Application\Dto;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration\SubmissionError;

/**
 * Read model of an error kept with a declaration record: the code, its kind (a `RejectionKind` value) and the URSSAF
 * message and description, which may echo host values (masked in debug output, never log them, ADR 0007).
 */
final readonly class SubmissionErrorView
{
    private function __construct(
        public string $code,
        public string $kind,
        #[SensitiveParameter]
        public ?string $message,
        #[SensitiveParameter]
        public ?string $description,
    ) {}

    /**
     * @param list<SubmissionError> $errors
     *
     * @return list<self>
     */
    public static function fromErrors(array $errors): array
    {
        return array_map(
            static fn(SubmissionError $error): self => new self($error->code(), $error->kind(), $error->message(), $error->description()),
            $errors,
        );
    }

    /**
     * @return array<string, string|null>
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
