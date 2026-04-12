<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Institution;
use App\Models\Logbook;
use App\Models\Period;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminEvaluationLabController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'institution_id' => ['nullable', 'integer', Rule::exists('institutions', 'id')],
            'period_id' => ['nullable', 'integer', Rule::exists('periods', 'id')],
            'intern_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'sort' => ['nullable', 'string', 'in:name,final_score,missing_days,submitted_days'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
        ]);

        $institutions = Institution::query()
            ->orderBy('name')
            ->get(['id', 'name', 'type']);

        $selectedInstitutionId = isset($validated['institution_id'])
            ? (int) $validated['institution_id']
            : (int) ($institutions->first()->id ?? 0);

        $periodOptions = Period::query()
            ->where('type', Period::TYPE_INTERNSHIP)
            ->when(
                $selectedInstitutionId > 0,
                static fn ($query) => $query->where('institution_id', $selectedInstitutionId),
            )
            ->orderByDesc('start_date')
            ->get(['id', 'name', 'institution_id', 'start_date', 'end_date', 'holidays']);

        $selectedPeriodId = isset($validated['period_id']) ? (int) $validated['period_id'] : 0;

        $selectedPeriod = $periodOptions
            ->first(static fn (Period $period): bool => (int) $period->id === $selectedPeriodId)
            ?? $periodOptions->first();

        $selectedPeriodId = (int) ($selectedPeriod?->id ?? 0);

        $internOptions = User::query()
            ->role('Intern')
            ->when(
                $selectedInstitutionId > 0,
                static fn ($query) => $query->where('institution_id', $selectedInstitutionId),
            )
            ->orderBy('name')
            ->get(['id', 'name', 'institution_id']);

        $selectedInternId = isset($validated['intern_id']) ? (int) $validated['intern_id'] : 0;
        if ($selectedInternId > 0 && ! $internOptions->contains(static fn (User $intern): bool => (int) $intern->id === $selectedInternId)) {
            $selectedInternId = 0;
        }

        $sort = (string) ($validated['sort'] ?? 'final_score');
        $direction = (string) ($validated['direction'] ?? 'desc');

        $rows = collect();
        $dailyDetails = [];
        $requiredDates = collect();

        if ($selectedPeriod instanceof Period) {
            $targetInterns = $selectedInternId > 0
                ? $internOptions->filter(static fn (User $intern): bool => (int) $intern->id === $selectedInternId)->values()
                : $internOptions;

            $requiredDates = $this->requiredDates($selectedPeriod);
            [$rows, $dailyDetails] = $this->buildEvaluationRows($selectedPeriod, $targetInterns, $requiredDates);

            $rows = $this->sortRows($rows, $sort, $direction)->values();
        }

        $detailInternId = $selectedInternId > 0
            ? $selectedInternId
            : (int) ($rows->first()['intern_id'] ?? 0);

        $detailRows = collect($dailyDetails[$detailInternId] ?? []);

        $aggregate = [
            'intern_count' => (int) $rows->count(),
            'required_days_total' => (int) $rows->sum('required_days'),
            'submitted_days_total' => (int) $rows->sum('submitted_days'),
            'missing_days_total' => (int) $rows->sum('missing_days'),
            'avg_final_score' => $rows->isEmpty() ? 0.0 : round((float) $rows->avg('final_score'), 2),
        ];

        return view('admin.evaluation-lab.index', [
            'institutions' => $institutions,
            'periodOptions' => $periodOptions,
            'internOptions' => $internOptions,
            'selectedPeriod' => $selectedPeriod,
            'rows' => $rows,
            'detailRows' => $detailRows,
            'detailInternId' => $detailInternId,
            'requiredDatesCount' => (int) $requiredDates->count(),
            'aggregate' => $aggregate,
            'filters' => [
                'institution_id' => $selectedInstitutionId > 0 ? $selectedInstitutionId : null,
                'period_id' => $selectedPeriodId > 0 ? $selectedPeriodId : null,
                'intern_id' => $selectedInternId > 0 ? $selectedInternId : null,
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
    }

    /**
     * @return Collection<int, string>
     */
    private function requiredDates(Period $period): Collection
    {
        $start = Carbon::parse((string) $period->start_date)->startOfDay();
        $end = Carbon::parse((string) $period->end_date)->startOfDay();

        $holidays = collect($period->holidays ?? [])
            ->map(static fn ($date): string => Carbon::parse((string) $date)->toDateString())
            ->flip();

        $dates = collect();
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $dateString = $cursor->toDateString();

            if (! $cursor->isWeekend() && ! $holidays->has($dateString)) {
                $dates->push($dateString);
            }

            $cursor->addDay();
        }

        return $dates;
    }

    /**
     * @param  Collection<int, User>  $targetInterns
     * @param  Collection<int, string>  $requiredDates
     * @return array{0: Collection<int, array{intern_id: int, name: string, required_days: int, submitted_days: int, missing_days: int, zero_days: int, final_score: float}>, 1: array<int, array<int, array{date: string, has_logbook: bool, task_count: int, score: float, note: string}>>}
     */
    private function buildEvaluationRows(Period $period, Collection $targetInterns, Collection $requiredDates): array
    {
        $internIds = $targetInterns
            ->pluck('id')
            ->map(static fn (int $id): int => (int) $id)
            ->values()
            ->all();

        if ($internIds === [] || $requiredDates->isEmpty()) {
            return [collect(), []];
        }

        $requiredDateSet = $requiredDates->flip();
        $rangeStart = Carbon::parse((string) $period->start_date)->startOfDay();
        $rangeEnd = Carbon::parse((string) $period->end_date)->endOfDay();

        $logbookMap = [];
        $logbooks = Logbook::query()
            ->where('period_id', $period->id)
            ->whereIn('user_id', $internIds)
            ->whereBetween('report_date', [$rangeStart->toDateString(), $rangeEnd->toDateString()])
            ->get(['user_id', 'report_date']);

        foreach ($logbooks as $logbook) {
            $dateKey = Carbon::parse((string) $logbook->report_date)->toDateString();
            $logbookMap[(int) $logbook->user_id][$dateKey] = true;
        }

        $taskScoresMap = [];
        $tasks = Project::query()
            ->whereIn('assignee_id', $internIds)
            ->where(function ($query) use ($rangeStart, $rangeEnd): void {
                $query
                    ->whereBetween('created_at', [$rangeStart, $rangeEnd])
                    ->orWhereBetween('updated_at', [$rangeStart, $rangeEnd]);
            })
            ->get(['id', 'assignee_id', 'priority', 'status', 'created_at', 'updated_at']);

        foreach ($tasks as $task) {
            $userId = (int) $task->assignee_id;
            $taskScore = $this->taskScore($task);

            $touchDates = collect([
                optional($task->created_at)->toDateString(),
                optional($task->updated_at)->toDateString(),
            ])->filter()->unique()->values();

            foreach ($touchDates as $dateKey) {
                if (! $requiredDateSet->has((string) $dateKey)) {
                    continue;
                }

                $taskScoresMap[$userId][(string) $dateKey][(int) $task->id] = $taskScore;
            }
        }

        $rows = collect();
        $dailyDetails = [];

        foreach ($targetInterns as $intern) {
            $internId = (int) $intern->id;
            $scoreTotal = 0.0;
            $submittedDays = 0;
            $missingDays = 0;
            $zeroDays = 0;
            $internDetails = [];

            foreach ($requiredDates as $dateKey) {
                $hasLogbook = (bool) ($logbookMap[$internId][$dateKey] ?? false);

                if (! $hasLogbook) {
                    $score = 0.0;
                    $missingDays++;
                    $zeroDays++;
                    $taskCount = 0;
                    $note = 'Tidak isi logbook di hari kerja non-libur (nilai 0).';
                } else {
                    $submittedDays++;

                    $taskScores = array_values($taskScoresMap[$internId][$dateKey] ?? []);
                    $taskCount = count($taskScores);

                    if ($taskCount === 0) {
                        $score = 2.0;
                        $note = 'Logbook ada, task terdeteksi 0 (nilai minimum eksperimen = 2).';
                    } else {
                        $score = $this->clamp((float) round(array_sum($taskScores) / $taskCount, 2), 2.0, 10.0);
                        $note = "Skor dari {$taskCount} task (priority + durasi pengerjaan).";
                    }
                }

                $scoreTotal += $score;

                $internDetails[] = [
                    'date' => $dateKey,
                    'has_logbook' => $hasLogbook,
                    'task_count' => $taskCount,
                    'score' => (float) $score,
                    'note' => $note,
                ];
            }

            $requiredDays = count($internDetails);
            $finalScore = $requiredDays > 0
                ? (float) round($scoreTotal / $requiredDays, 2)
                : 0.0;

            $rows->push([
                'intern_id' => $internId,
                'name' => $intern->name,
                'required_days' => $requiredDays,
                'submitted_days' => $submittedDays,
                'missing_days' => $missingDays,
                'zero_days' => $zeroDays,
                'final_score' => $finalScore,
            ]);

            $dailyDetails[$internId] = $internDetails;
        }

        return [$rows, $dailyDetails];
    }

    private function taskScore(Project $task): float
    {
        $priorityWeight = match ((string) $task->priority) {
            'low' => 2.0,
            'medium' => 4.0,
            'high' => 7.0,
            'critical' => 10.0,
            default => 2.0,
        };

        $durationHours = max(
            1,
            (int) Carbon::parse((string) $task->created_at)
                ->diffInHours(Carbon::parse((string) $task->updated_at))
        );

        $durationMultiplier = match (true) {
            $durationHours <= 8 => 1.0,
            $durationHours <= 24 => 0.9,
            $durationHours <= 72 => 0.8,
            $durationHours <= 168 => 0.7,
            default => 0.6,
        };

        $statusMultiplier = match ((string) $task->status) {
            'done' => 1.0,
            'doing' => 0.9,
            'todo' => 0.75,
            default => 0.75,
        };

        $score = $priorityWeight * $durationMultiplier * $statusMultiplier;

        return $this->clamp((float) round($score, 2), 2.0, 10.0);
    }

    /**
     * @param  Collection<int, array{intern_id: int, name: string, required_days: int, submitted_days: int, missing_days: int, zero_days: int, final_score: float}>  $rows
     * @return Collection<int, array{intern_id: int, name: string, required_days: int, submitted_days: int, missing_days: int, zero_days: int, final_score: float}>
     */
    private function sortRows(Collection $rows, string $sort, string $direction): Collection
    {
        $sorted = match ($sort) {
            'name' => $rows->sortBy('name'),
            'missing_days' => $rows->sortBy('missing_days'),
            'submitted_days' => $rows->sortBy('submitted_days'),
            default => $rows->sortBy('final_score'),
        };

        return $direction === 'asc' ? $sorted->values() : $sorted->reverse()->values();
    }

    private function clamp(float $value, float $min, float $max): float
    {
        if ($value < $min) {
            return $min;
        }

        if ($value > $max) {
            return $max;
        }

        return $value;
    }
}
