<?php

namespace App\Support;

use Illuminate\Support\Carbon;

class SprintWindow
{
    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function resolveRange(?Carbon $baseDate = null, bool $shiftWeekendToNextWorkday = true): array
    {
        $date = ($baseDate ?? now())->copy()->startOfDay();
        $workdays = self::workdays();

        if ($shiftWeekendToNextWorkday && ! in_array($date->dayOfWeekIso, $workdays, true)) {
            $date = self::nextWorkday($date, $workdays);
        }

        $spanWeeks = max(1, (int) config('sprint.span_weeks', 2));
        $referenceMonday = self::referenceMonday();
        $targetMonday = $date->copy()->startOfWeek(Carbon::MONDAY);

        $diffWeeks = (int) floor($referenceMonday->diffInDays($targetMonday, false) / 7);
        $cycleIndex = self::floorDiv($diffWeeks, $spanWeeks);

        $start = $referenceMonday->copy()->addWeeks($cycleIndex * $spanWeeks);

        $workdayEnd = max($workdays);
        $end = $start->copy()
            ->addWeeks($spanWeeks - 1)
            ->startOfWeek(Carbon::MONDAY)
            ->addDays($workdayEnd - 1)
            ->endOfDay();

        return [$start->startOfDay(), $end];
    }

    public static function formatName(Carbon $start, Carbon $end): string
    {
        return sprintf('Sprint %s - %s', $start->toDateString(), $end->toDateString());
    }

    /**
     * @return array<int, int>
     */
    private static function workdays(): array
    {
        $configured = config('sprint.workdays_iso', [1, 2, 3, 4, 5]);

        if (! is_array($configured) || $configured === []) {
            return [1, 2, 3, 4, 5];
        }

        $days = array_values(array_unique(array_map(static fn ($day): int => (int) $day, $configured)));
        sort($days);

        $days = array_values(array_filter($days, static fn (int $day): bool => $day >= 1 && $day <= 7));

        return $days !== [] ? $days : [1, 2, 3, 4, 5];
    }

    private static function referenceMonday(): Carbon
    {
        $reference = Carbon::parse((string) config('sprint.reference_monday', '2026-01-05'));

        return $reference->startOfWeek(Carbon::MONDAY)->startOfDay();
    }

    /**
     * Floor division that behaves correctly for negative values.
     */
    private static function floorDiv(int $a, int $b): int
    {
        $q = intdiv($a, $b);
        $r = $a % $b;

        if ($r !== 0 && (($r < 0) xor ($b < 0))) {
            return $q - 1;
        }

        return $q;
    }

    /**
     * @param  array<int, int>  $workdays
     */
    private static function nextWorkday(Carbon $date, array $workdays): Carbon
    {
        $next = $date->copy();

        do {
            $next->addDay();
        } while (! in_array($next->dayOfWeekIso, $workdays, true));

        return $next;
    }
}
