<?php

namespace App\Http\Controllers\Intern;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;

class InternDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->hasRole('Intern')) {
            abort(403);
        }

        $today = Carbon::today();
        $baseTaskQuery = Project::query()->where('assignee_id', $user->id);

        $statusCounts = $baseTaskQuery
            ->clone()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $priorityCounts = $baseTaskQuery
            ->clone()
            ->select('priority', DB::raw('COUNT(*) as total'))
            ->groupBy('priority')
            ->pluck('total', 'priority');

        $totalTasks = array_sum([
            (int) ($statusCounts['todo'] ?? 0),
            (int) ($statusCounts['doing'] ?? 0),
            (int) ($statusCounts['done'] ?? 0),
        ]);

        $doneAllTime = (int) ($statusCounts['done'] ?? 0);
        $openTasks = (int) ($statusCounts['todo'] ?? 0) + (int) ($statusCounts['doing'] ?? 0);

        $doneThisWeek = $baseTaskQuery
            ->clone()
            ->where('status', 'done')
            ->whereBetween('updated_at', [
                $today->copy()->startOfWeek(Carbon::MONDAY),
                $today->copy()->endOfWeek(Carbon::SUNDAY),
            ])
            ->count();

        $doneThisMonth = $baseTaskQuery
            ->clone()
            ->where('status', 'done')
            ->whereBetween('updated_at', [
                $today->copy()->startOfMonth(),
                $today->copy()->endOfMonth(),
            ])
            ->count();

        $overdueOpen = $baseTaskQuery
            ->clone()
            ->whereDate('due_date', '<', $today->toDateString())
            ->where('status', '!=', 'done')
            ->count();

        $dueSoon = $baseTaskQuery
            ->clone()
            ->whereBetween('due_date', [
                $today->toDateString(),
                $today->copy()->addDays(3)->toDateString(),
            ])
            ->where('status', '!=', 'done')
            ->count();

        $completionRate = $totalTasks > 0 ? round(($doneAllTime / $totalTasks) * 100, 1) : 0.0;

        $taskIds = $baseTaskQuery->clone()->pluck('id')->map(static fn (int $id): int => (int) $id)->all();

        $commentCount = $taskIds === []
            ? 0
            : DB::table('comments')
                ->where('commentable_type', Project::class)
                ->whereIn('commentable_id', $taskIds)
                ->count();

        $doneTrend = $this->doneTrendDataset($user->id, $today);

        $projectContribution = $baseTaskQuery
            ->clone()
            ->with('spec:id,title')
            ->select('project_spec_id', DB::raw('COUNT(*) as total'))
            ->groupBy('project_spec_id')
            ->orderByDesc('total')
            ->limit(6)
            ->get()
            ->map(static function (Project $task): array {
                return [
                    'project' => $task->spec?->title ?? 'General',
                    'total' => (int) $task->total,
                ];
            })
            ->values();

        $focusTasks = $baseTaskQuery
            ->clone()
            ->with('spec:id,title')
            ->where('status', '!=', 'done')
            ->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_date')
            ->orderByDesc('priority')
            ->limit(8)
            ->get(['id', 'title', 'project_spec_id', 'status', 'priority', 'due_date']);

        $recentActivities = $taskIds === []
            ? collect()
            : Activity::query()
                ->where('subject_type', Project::class)
                ->whereIn('subject_id', $taskIds)
                ->latest()
                ->limit(10)
                ->get(['description', 'event', 'created_at']);

        return view('intern.dashboard', [
            'kpi' => [
                'open_tasks' => $openTasks,
                'done_all_time' => $doneAllTime,
                'done_this_week' => $doneThisWeek,
                'done_this_month' => $doneThisMonth,
                'overdue_open' => $overdueOpen,
                'due_soon' => $dueSoon,
                'completion_rate' => $completionRate,
                'comment_count' => $commentCount,
            ],
            'charts' => [
                'status_labels' => ['todo', 'doing', 'done'],
                'status_values' => [
                    (int) ($statusCounts['todo'] ?? 0),
                    (int) ($statusCounts['doing'] ?? 0),
                    (int) ($statusCounts['done'] ?? 0),
                ],
                'priority_labels' => ['low', 'medium', 'high', 'critical'],
                'priority_values' => [
                    (int) ($priorityCounts['low'] ?? 0),
                    (int) ($priorityCounts['medium'] ?? 0),
                    (int) ($priorityCounts['high'] ?? 0),
                    (int) ($priorityCounts['critical'] ?? 0),
                ],
                'done_trend_labels' => $doneTrend['labels'],
                'done_trend_values' => $doneTrend['values'],
                'project_labels' => $projectContribution->pluck('project')->all(),
                'project_values' => $projectContribution->pluck('total')->all(),
            ],
            'focusTasks' => $focusTasks,
            'recentActivities' => $recentActivities,
        ]);
    }

    /**
     * @return array{labels: array<int, string>, values: array<int, int>}
     */
    private function doneTrendDataset(int $userId, Carbon $today): array
    {
        $start = $today->copy()->subDays(13)->startOfDay();
        $end = $today->copy()->endOfDay();

        $raw = Project::query()
            ->where('assignee_id', $userId)
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
}
