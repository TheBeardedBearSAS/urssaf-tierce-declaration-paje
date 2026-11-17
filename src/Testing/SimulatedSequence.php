<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Testing;

/**
 * Numbered identifiers of the simulator (1, 2, 3...), formatted with a sprintf format holding one `%d`.
 *
 * @internal
 */
final class SimulatedSequence
{
    private int $last = 0;

    public function __construct(private readonly string $format) {}

    public function next(): string
    {
        ++$this->last;

        return \sprintf($this->format, $this->last);
    }
}
