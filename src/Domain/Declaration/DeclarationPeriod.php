<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Declaration;

use TheBeardedBear\UrssafTierceDeclarationPaje\Domain\Shared\CalendarDate;

/**
 * Service period and payment date of a declaration (`dtDebutPeriode`, `dtFinPeriode`, `dtPaiementSalaire`).
 *
 * The contract states no ordering rule between the three dates (the opening window and `ER_API_DECLA_00071` are
 * server-side, ADR 0009), so none is enforced here: only the strict `yyyy-MM-dd` format of {@see CalendarDate}.
 */
final readonly class DeclarationPeriod
{
    private function __construct(
        private CalendarDate $start,
        private CalendarDate $end,
        private CalendarDate $paymentDate,
    ) {}

    public static function of(CalendarDate $start, CalendarDate $end, CalendarDate $paymentDate): self
    {
        return new self($start, $end, $paymentDate);
    }

    /**
     * `dtDebutPeriode`.
     */
    public function start(): CalendarDate
    {
        return $this->start;
    }

    /**
     * `dtFinPeriode`.
     */
    public function end(): CalendarDate
    {
        return $this->end;
    }

    /**
     * `dtPaiementSalaire`.
     */
    public function paymentDate(): CalendarDate
    {
        return $this->paymentDate;
    }

    public function equals(self $other): bool
    {
        return $this->start->equals($other->start)
            && $this->end->equals($other->end)
            && $this->paymentDate->equals($other->paymentDate);
    }
}
