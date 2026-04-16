<?php

namespace App\Support;

use App\Models\Period;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;

class SprintPeriodResolver
{
    /**
     * @return array{0: ?Period, 1: bool}
     */
    public static function resolveForInstitution(int $institutionId, ?Carbon $baseDate = null, bool $createIfMissing = true): array
    {
        [$startDate, $endDate] = SprintWindow::resolveRange($baseDate ?? Carbon::now(), true);

        $exactSprint = Period::query()
            ->where('type', Period::TYPE_SPRINT)
            ->where('institution_id', $institutionId)
            ->whereDate('start_date', $startDate->toDateString())
            ->whereDate('end_date', $endDate->toDateString())
            ->orderBy('id')
            ->first();

        if ($exactSprint) {
            return [$exactSprint, false];
        }

        // Prevent duplicate days by reusing overlapping sprint in the same institution.
        $overlappingSprint = Period::query()
            ->where('type', Period::TYPE_SPRINT)
            ->where('institution_id', $institutionId)
            ->whereDate('start_date', '<=', $endDate->toDateString())
            ->whereDate('end_date', '>=', $startDate->toDateString())
            ->orderBy('start_date')
            ->orderBy('id')
            ->first();

        if ($overlappingSprint) {
            return [$overlappingSprint, false];
        }

        if ( ! $createIfMissing) {
            return [null, false];
        }

        try {
            $sprint = Period::query()->firstOrCreate(
                [
                    'institution_id' => $institutionId,
                    'type' => Period::TYPE_SPRINT,
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                ],
                [
                    'name' => SprintWindow::formatName($startDate, $endDate),
                    'holidays' => [],
                ]
            );
        } catch (QueryException) {
            $sprint = Period::query()
                ->where('institution_id', $institutionId)
                ->where('type', Period::TYPE_SPRINT)
                ->whereDate('start_date', $startDate->toDateString())
                ->whereDate('end_date', $endDate->toDateString())
                ->orderBy('id')
                ->first();
        }

        if ( ! $sprint) {
            return [null, false];
        }

        return [$sprint, $sprint->wasRecentlyCreated];
    }
}
