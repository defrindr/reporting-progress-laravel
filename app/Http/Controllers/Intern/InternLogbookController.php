<?php

namespace App\Http\Controllers\Intern;

use App\Http\Controllers\Controller;
use App\Http\Requests\LogbookRequest;
use App\Models\Logbook;
use App\Models\Period;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InternLogbookController extends Controller
{
    public function index(): View
    {
        $user = Auth::user();
        $accessState = $this->internAccessState($user);

        return view('logbook', [
            'logbooks' => Logbook::query()
                ->where('user_id', $user->id)
                ->with('period:id,name')
                ->latest('report_date')
                ->get(),
            'isInternReadOnly' => (bool) $accessState['is_read_only'],
            'readOnlyReason' => $accessState['reason'],
            'isWeekendLock' => now()->isWeekend(),
        ]);
    }

    public function store(LogbookRequest $request): RedirectResponse
    {
        $user = $request->user();
        $accessState = $this->internAccessState($user);

        if ($accessState['is_read_only']) {
            return back()->withErrors(['report_date' => $accessState['reason']])->withInput();
        }

        $data = $request->validated();
        $reportDate = $data['report_date'];

        if (Carbon::parse((string) $reportDate)->isWeekend()) {
            return back()->withErrors([
                'report_date' => 'Tidak bisa buat report untuk hari Sabtu/Minggu.',
            ])->withInput();
        }

        if (! $user->institution_id) {
            return back()->withErrors(['report_date' => 'Akun intern harus terhubung ke institusi.'])->withInput();
        }

        $activePeriod = Period::query()
            ->where('institution_id', $user->institution_id)
            ->where('type', Period::TYPE_INTERNSHIP)
            ->whereDate('start_date', '<=', $reportDate)
            ->whereDate('end_date', '>=', $reportDate)
            ->first();

        if (! $activePeriod) {
            return back()->withErrors(['report_date' => 'Tidak ada periode aktif untuk tanggal ini.'])->withInput();
        }

        if (in_array($reportDate, $activePeriod->holidays ?? [], true)) {
            return back()->withErrors(['report_date' => 'Cannot submit reports on holidays'])->withInput();
        }

        $logbook = Logbook::create([
            'user_id' => $user->id,
            'period_id' => $activePeriod->id,
            'report_date' => $reportDate,
            'done_tasks' => $data['done_tasks'],
            'next_tasks' => $data['next_tasks'],
            'appendix_link' => $data['appendix_link'] ?? null,
            'status' => 'submitted',
        ]);

        return redirect()->route('logbook.form')->with('status', 'Report logbook berhasil disubmit.');
    }

    public function taskResume(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'report_date' => ['required', 'date'],
            'scope' => ['nullable', Rule::in(['daily', 'weekly'])],
            'use_ai' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();
        $scope = (string) ($validated['scope'] ?? 'daily');
        $useAi = (bool) ($validated['use_ai'] ?? false);

        $reportDate = Carbon::parse((string) $validated['report_date']);

        if ($scope === 'weekly') {
            $start = $reportDate->copy()->startOfWeek(Carbon::MONDAY);
            $end = $reportDate->copy()->endOfWeek(Carbon::SUNDAY);
            $rangeLabel = sprintf('mingguan %s - %s', $start->toDateString(), $end->toDateString());
        } else {
            $start = $reportDate->copy()->startOfDay();
            $end = $reportDate->copy()->endOfDay();
            $rangeLabel = sprintf('harian %s', $reportDate->toDateString());
        }

        $tasks = Project::query()
            ->with('spec:id,title')
            ->where('assignee_id', $user->id)
            ->where(function ($query) use ($start, $end): void {
                $query->whereBetween('updated_at', [$start, $end])
                    ->orWhereBetween('created_at', [$start, $end])
                    ->orWhereBetween('due_date', [$start->toDateString(), $end->toDateString()]);
            })
            ->orderByDesc('updated_at')
            ->limit(120)
            ->get();

        $doneTasks = $tasks->where('status', 'done')->values();
        $doingTasks = $tasks->where('status', 'doing')->values();
        $todoTasks = $tasks->where('status', 'todo')->values();

        if ($useAi) {
            [$doneText, $nextText] = $this->buildLocalAiResume(
                rangeLabel: $rangeLabel,
                tasks: $tasks,
                doneTasks: $doneTasks,
                doingTasks: $doingTasks,
                todoTasks: $todoTasks,
            );
        } else {
            [$doneText, $nextText] = $this->buildManualResume(
                rangeLabel: $rangeLabel,
                doneTasks: $doneTasks,
                doingTasks: $doingTasks,
                todoTasks: $todoTasks,
            );
        }

        return response()->json([
            'done_tasks' => $doneText,
            'next_tasks' => $nextText,
            'tasks' => $tasks->take(20)->map(function (Project $task): array {
                return [
                    'id' => $task->id,
                    'project' => $task->spec?->title ?? '-',
                    'title' => $task->title,
                    'status' => $task->status,
                    'priority' => $task->priority,
                    'due_date' => optional($task->due_date)->toDateString(),
                    'updated_at' => optional($task->updated_at)->toDateTimeString(),
                ];
            })->values()->all(),
            'meta' => [
                'mode' => $scope,
                'range' => $rangeLabel,
                'generator' => $useAi ? 'ai-local' : 'manual-template',
                'total_tasks' => $tasks->count(),
                'done' => $doneTasks->count(),
                'doing' => $doingTasks->count(),
                'todo' => $todoTasks->count(),
            ],
        ]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function buildManualResume(string $rangeLabel, Collection $doneTasks, Collection $doingTasks, Collection $todoTasks): array
    {
        $doneLines = $doneTasks
            ->take(12)
            ->map(function (Project $task): string {
                $projectName = $task->spec?->title ?? 'General';

                return sprintf('- [%s] %s (%s)', strtoupper((string) $task->priority), $task->title, $projectName);
            })
            ->implode("\n");

        $nextLines = $doingTasks
            ->concat($todoTasks)
            ->sortBy('due_date')
            ->take(12)
            ->map(function (Project $task): string {
                $due = optional($task->due_date)->toDateString() ?? '-';
                $projectName = $task->spec?->title ?? 'General';

                return sprintf('- %s (%s) | due %s | status %s', $task->title, $projectName, $due, $task->status);
            })
            ->implode("\n");

        $doneText = $doneLines !== ''
            ? "Resume {$rangeLabel}:\n{$doneLines}"
            : "Resume {$rangeLabel}: belum ada task selesai yang terdeteksi dari project board.";

        $nextText = $nextLines !== ''
            ? "Rencana lanjutan:\n{$nextLines}"
            : 'Rencana lanjutan: review backlog baru, update status task, dan sinkronisasi target sprint berikutnya.';

        return [$doneText, $nextText];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function buildLocalAiResume(
        string $rangeLabel,
        Collection $tasks,
        Collection $doneTasks,
        Collection $doingTasks,
        Collection $todoTasks
    ): array {
        $projectFocus = $tasks
            ->groupBy(static fn (Project $task): string => $task->spec?->title ?? 'General')
            ->map(static fn (Collection $group): int => $group->count())
            ->sortDesc()
            ->keys()
            ->take(3)
            ->values();

        $focus = $projectFocus->isNotEmpty() ? $projectFocus->implode(', ') : 'belum ada fokus project';

        $doneSummary = sprintf(
            'Ringkasan AI lokal tanpa API key untuk %s: total %d task terpantau, %d selesai, %d sedang dikerjakan, %d belum mulai. Fokus utama: %s.',
            $rangeLabel,
            $tasks->count(),
            $doneTasks->count(),
            $doingTasks->count(),
            $todoTasks->count(),
            $focus,
        );

        $doneHighlights = $doneTasks
            ->take(8)
            ->map(function (Project $task): string {
                $projectName = $task->spec?->title ?? 'General';

                return sprintf('- Menyelesaikan %s pada project %s.', $task->title, $projectName);
            })
            ->implode("\n");

        $nextHighlights = $doingTasks
            ->concat($todoTasks)
            ->sortBy('due_date')
            ->take(8)
            ->map(function (Project $task): string {
                $due = optional($task->due_date)->toDateString() ?? '-';
                $projectName = $task->spec?->title ?? 'General';

                return sprintf('- Lanjutkan %s pada project %s (target %s).', $task->title, $projectName, $due);
            })
            ->implode("\n");

        $doneText = $doneSummary;
        if ($doneHighlights !== '') {
            $doneText .= "\n\nHighlight progress:\n{$doneHighlights}";
        }

        $nextText = 'Prioritas lanjutan yang disarankan AI lokal:';
        if ($nextHighlights !== '') {
            $nextText .= "\n{$nextHighlights}";
        } else {
            $nextText .= "\n- Belum ada task aktif. Ambil backlog baru sesuai sprint berjalan.";
        }

        return [$doneText, $nextText];
    }

    /**
     * @return array{is_read_only: bool, reason: ?string}
     */
    private function internAccessState($user): array
    {
        if (! $user || ! $user->institution_id) {
            return [
                'is_read_only' => true,
                'reason' => 'Akun intern harus terhubung ke institusi dan period magang aktif.',
            ];
        }

        $today = now()->toDateString();

        $activeInternship = Period::query()
            ->where('institution_id', $user->institution_id)
            ->where('type', Period::TYPE_INTERNSHIP)
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->exists();

        if ($activeInternship) {
            return [
                'is_read_only' => false,
                'reason' => null,
            ];
        }

        $latestInternship = Period::query()
            ->where('institution_id', $user->institution_id)
            ->where('type', Period::TYPE_INTERNSHIP)
            ->orderByDesc('end_date')
            ->first();

        if ($latestInternship && Carbon::parse((string) $latestInternship->end_date)->lt(now()->startOfDay())) {
            return [
                'is_read_only' => true,
                'reason' => 'Periode magang sudah selesai. Semua fitur kini read-only.',
            ];
        }

        return [
            'is_read_only' => true,
            'reason' => 'Tidak ada periode aktif untuk tanggal ini.',
        ];
    }
}
