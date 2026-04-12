<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Institution;
use App\Models\Logbook;
use App\Models\Period;
use App\Models\Project;
use App\Models\ProjectSpec;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\View\View;
use Spatie\Permission\Models\Role;

class AdminDashboardController extends Controller
{
    public function index(): View
    {
        $today = Carbon::today();
        $activeInstitutionIds = $this->activeInternshipInstitutionIds($today);

        $activeInterns = User::query()
            ->role('Intern')
            ->when(
                $activeInstitutionIds !== [],
                fn ($query) => $query->whereIn('institution_id', $activeInstitutionIds),
                fn ($query) => $query->whereRaw('1 = 0'),
            )
            ->orderBy('name')
            ->get(['id', 'name', 'institution_id']);

        $activeInternIds = $activeInterns
            ->pluck('id')
            ->map(static fn (int $id): int => (int) $id)
            ->all();

        $statusCounts = $this->taskStatusCounts($activeInternIds);
        $totalTasks = array_sum($statusCounts);
        $doneTasks = $statusCounts['done'] ?? 0;
        $overdueOpenTasks = Project::query()
            ->when(
                $activeInternIds !== [],
                fn ($query) => $query->whereIn('assignee_id', $activeInternIds),
                fn ($query) => $query->whereRaw('1 = 0'),
            )
            ->whereDate('due_date', '<', $today->toDateString())
            ->where('status', '!=', 'done')
            ->count();

        $submittedTodayInterns = Logbook::query()
            ->when(
                $activeInternIds !== [],
                fn ($query) => $query->whereIn('user_id', $activeInternIds),
                fn ($query) => $query->whereRaw('1 = 0'),
            )
            ->whereDate('report_date', $today->toDateString())
            ->distinct('user_id')
            ->count('user_id');

        $weeklyDoneTasks = Project::query()
            ->when(
                $activeInternIds !== [],
                fn ($query) => $query->whereIn('assignee_id', $activeInternIds),
                fn ($query) => $query->whereRaw('1 = 0'),
            )
            ->where('status', 'done')
            ->whereBetween('updated_at', [
                $today->copy()->subDays(6)->startOfDay(),
                $today->copy()->endOfDay(),
            ])
            ->count();

        $activeInternCount = count($activeInternIds);
        $completionRate = $totalTasks > 0 ? round(($doneTasks / $totalTasks) * 100, 1) : 0.0;
        $submissionRateToday = $activeInternCount > 0 ? round(($submittedTodayInterns / $activeInternCount) * 100, 1) : 0.0;
        $avgDonePerInternWeek = $activeInternCount > 0 ? round($weeklyDoneTasks / $activeInternCount, 2) : 0.0;

        $doneTrend = $this->doneTrendDataset($activeInternIds, $today);
        $topInterns = $this->topInternKpiRows($activeInterns, $today);

        return view('admin.dashboard', [
            'stats' => [
                'users' => User::count(),
                'roles' => Role::count(),
                'institutions' => Institution::count(),
                'periods' => Period::count(),
                'project_specs' => ProjectSpec::count(),
                'tasks' => Project::count(),
                'logbooks' => Logbook::count(),
            ],
            'kpi' => [
                'active_interns' => $activeInternCount,
                'completion_rate' => $completionRate,
                'overdue_open_tasks' => $overdueOpenTasks,
                'submission_rate_today' => $submissionRateToday,
                'avg_done_per_intern_week' => $avgDonePerInternWeek,
            ],
            'charts' => [
                'status_labels' => ['todo', 'doing', 'done'],
                'status_values' => [
                    $statusCounts['todo'] ?? 0,
                    $statusCounts['doing'] ?? 0,
                    $statusCounts['done'] ?? 0,
                ],
                'done_trend_labels' => $doneTrend['labels'],
                'done_trend_values' => $doneTrend['values'],
                'top_intern_labels' => $topInterns->pluck('name')->values()->all(),
                'top_intern_done_values' => $topInterns->pluck('done_count')->values()->all(),
            ],
            'topInterns' => $topInterns,
        ]);
    }

    /**
     * @return array<int, int>
     */
    private function activeInternshipInstitutionIds(Carbon $today): array
    {
        return Period::query()
            ->where('type', Period::TYPE_INTERNSHIP)
            ->whereDate('start_date', '<=', $today->toDateString())
            ->whereDate('end_date', '>=', $today->toDateString())
            ->pluck('institution_id')
            ->filter()
            ->map(static fn (int $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int>  $activeInternIds
     * @return array{todo:int, doing:int, done:int}
     */
    private function taskStatusCounts(array $activeInternIds): array
    {
        $raw = Project::query()
            ->when(
                $activeInternIds !== [],
                fn ($query) => $query->whereIn('assignee_id', $activeInternIds),
                fn ($query) => $query->whereRaw('1 = 0'),
            )
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'todo' => (int) ($raw['todo'] ?? 0),
            'doing' => (int) ($raw['doing'] ?? 0),
            'done' => (int) ($raw['done'] ?? 0),
        ];
    }

    /**
     * @param  array<int, int>  $activeInternIds
     * @return array{labels: array<int, string>, values: array<int, int>}
     */
    private function doneTrendDataset(array $activeInternIds, Carbon $today): array
    {
        $start = $today->copy()->subDays(13)->startOfDay();
        $end = $today->copy()->endOfDay();

        $raw = Project::query()
            ->when(
                $activeInternIds !== [],
                fn ($query) => $query->whereIn('assignee_id', $activeInternIds),
                fn ($query) => $query->whereRaw('1 = 0'),
            )
            ->where('status', 'done')
            ->whereBetween('updated_at', [$start, $end])
            ->selectRaw('DATE(updated_at) as date_key, COUNT(*) as total')
            ->groupBy('date_key')
            ->pluck('total', 'date_key');

        $labels = [];
        $values = [];

        for ($i = 13; $i >= 0; $i--) {
            $date = $today->copy()->subDays($i);
            $key = $date->toDateString();

            $labels[] = $date->format('d M');
            $values[] = (int) ($raw[$key] ?? 0);
        }

        return [
            'labels' => $labels,
            'values' => $values,
        ];
    }

    /**
     * @param  Collection<int, User>  $activeInterns
     * @return Collection<int, array{name: string, done_count: int, completion_rate: float, overdue_open: int, weekly_logbooks: int}>
     */
    private function topInternKpiRows(Collection $activeInterns, Carbon $today): Collection
    {
        $activeInternIds = $activeInterns->pluck('id')->map(static fn (int $id): int => (int) $id)->all();

        if ($activeInternIds === []) {
            return collect();
        }

        $taskTotals = Project::query()
            ->whereIn('assignee_id', $activeInternIds)
            ->select('assignee_id', DB::raw('COUNT(*) as total'))
            ->groupBy('assignee_id')
            ->pluck('total', 'assignee_id');

        $doneTotals = Project::query()
            ->whereIn('assignee_id', $activeInternIds)
            ->where('status', 'done')
            ->select('assignee_id', DB::raw('COUNT(*) as total'))
            ->groupBy('assignee_id')
            ->pluck('total', 'assignee_id');

        $overdueTotals = Project::query()
            ->whereIn('assignee_id', $activeInternIds)
            ->whereDate('due_date', '<', $today->toDateString())
            ->where('status', '!=', 'done')
            ->select('assignee_id', DB::raw('COUNT(*) as total'))
            ->groupBy('assignee_id')
            ->pluck('total', 'assignee_id');

        $weekStart = $today->copy()->startOfWeek(Carbon::MONDAY)->toDateString();
        $weekEnd = $today->copy()->endOfWeek(Carbon::SUNDAY)->toDateString();

        $weeklyLogbookTotals = Logbook::query()
            ->whereIn('user_id', $activeInternIds)
            ->whereBetween('report_date', [$weekStart, $weekEnd])
            ->select('user_id', DB::raw('COUNT(*) as total'))
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        return $activeInterns
            ->map(function (User $intern) use ($taskTotals, $doneTotals, $overdueTotals, $weeklyLogbookTotals): array {
                $total = (int) ($taskTotals[$intern->id] ?? 0);
                $done = (int) ($doneTotals[$intern->id] ?? 0);
                $overdue = (int) ($overdueTotals[$intern->id] ?? 0);

                return [
                    'name' => $intern->name,
                    'done_count' => $done,
                    'completion_rate' => $total > 0 ? round(($done / $total) * 100, 1) : 0.0,
                    'overdue_open' => $overdue,
                    'weekly_logbooks' => (int) ($weeklyLogbookTotals[$intern->id] ?? 0),
                ];
            })
            ->sortByDesc('done_count')
            ->take(10)
            ->values();
    }
}
