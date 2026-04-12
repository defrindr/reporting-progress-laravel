<?php

namespace App\Http\Controllers\Intern;

use App\Http\Controllers\Controller;
use App\Models\Period;
use App\Models\Project;
use App\Models\ProjectSpec;
use App\Support\SprintWindow;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;

class InternProjectBoardController extends Controller
{
    public function index(Request $request): View
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            abort(403);
        }

        $isManager = $user->canManageAllProjects();
        $canReassign = $user->isAdmin();
        $viewMode = $request->string('view_mode')->toString();
        if (! in_array($viewMode, ['kanban', 'table'], true)) {
            $viewMode = 'kanban';
        }

        $statusFilter = $request->string('status')->toString();
        if (! in_array($statusFilter, ['todo', 'doing', 'done'], true)) {
            $statusFilter = null;
        }

        $filters = [
            'project_spec_id' => $request->integer('project_spec_id') > 0 ? $request->integer('project_spec_id') : null,
            'assignee_id' => $request->integer('assignee_id') > 0 ? $request->integer('assignee_id') : null,
            'status' => $statusFilter,
            'overdue' => $request->boolean('overdue'),
            'keyword' => trim($request->string('keyword')->toString()),
        ];

        $accessState = $isManager ? ['is_read_only' => false, 'reason' => null] : $this->internAccessState($user);
        $isWeekendRestriction = ! $isManager && now()->isWeekend();
        $nextWeekStartDate = now()->startOfWeek(Carbon::MONDAY)->addWeek()->toDateString();

        $sprints = Period::query()
            ->where('type', Period::TYPE_SPRINT)
            ->with('institution:id,name')
            ->when(! $isManager, fn ($query) => $query->where('institution_id', $user->institution_id))
            ->orderByDesc('start_date')
            ->get();

        $selectedSprintId = $request->integer('sprint_id');
        $selectedSprint = $sprints->firstWhere('id', $selectedSprintId);

        if (! $selectedSprint) {
            [$targetStart, $targetEnd] = SprintWindow::resolveRange(Carbon::now(), true);

            $selectedSprint = $sprints->first(function (Period $period) use ($targetStart, $targetEnd): bool {
                return optional($period->start_date)->toDateString() === $targetStart->toDateString()
                    && optional($period->end_date)->toDateString() === $targetEnd->toDateString();
            });
        }

        if (! $selectedSprint) {
            $selectedSprint = $sprints->first(function (Period $period): bool {
                $today = now()->toDateString();
                $startDate = Carbon::parse((string) $period->start_date)->toDateString();
                $endDate = Carbon::parse((string) $period->end_date)->toDateString();

                return $today >= $startDate && $today <= $endDate;
            }) ?? $sprints->first();
        }

        if ($selectedSprint && ! $accessState['is_read_only']) {
            $this->carryOverUnfinishedTasks($selectedSprint, $user, $isManager);
        }

        $sprintTimeline = $sprints
            ->sortBy(static fn (Period $period) => optional($period->start_date)->toDateString())
            ->values();

        $selectedIndex = $selectedSprint
            ? $sprintTimeline->search(static fn (Period $period): bool => $period->id === $selectedSprint->id)
            : false;

        $previousSprintId = null;
        $nextSprintId = null;

        if ($selectedIndex !== false) {
            if ($selectedIndex > 0) {
                $previousSprintId = $sprintTimeline->get($selectedIndex - 1)?->id;
            }

            if ($selectedIndex < $sprintTimeline->count() - 1) {
                $nextSprintId = $sprintTimeline->get($selectedIndex + 1)?->id;
            }
        }

        $availableProjects = $isManager
            ? collect()
            : $user->assignedProjectSpecs()
                ->orderBy('project_specs.title')
                ->get(['project_specs.id', 'project_specs.title']);

        $projectFilters = $isManager
            ? ProjectSpec::query()->orderBy('title')->get(['id', 'title'])
            : $availableProjects;

        $assigneeFilters = $isManager
            ? $this->activeInternsQuery($selectedSprint?->institution_id)->orderBy('name')->get(['id', 'name'])
            : collect();

        $taskQuery = Project::query()
            ->with(['spec:id,title,specification', 'assignee:id,name', 'creator:id,name', 'sprint:id,name,start_date,end_date', 'comments.user:id,name'])
            ->when(! $isManager, fn ($query) => $query->where('assignee_id', $user->id))
            ->when($filters['project_spec_id'], fn ($query, $value) => $query->where('project_spec_id', (int) $value))
            ->when($isManager && $filters['assignee_id'], fn ($query, $value) => $query->where('assignee_id', (int) $value))
            ->when($filters['status'], fn ($query, $value) => $query->where('status', $value))
            ->when($filters['overdue'], fn ($query) => $query
                ->whereDate('due_date', '<', now()->toDateString())
                ->where('status', '!=', 'done'))
            ->when($filters['keyword'] !== '', function ($query) use ($filters): void {
                $keyword = '%'.$filters['keyword'].'%';

                $query->where(function ($innerQuery) use ($keyword): void {
                    $innerQuery
                        ->where('title', 'like', $keyword)
                        ->orWhere('description', 'like', $keyword)
                        ->orWhereHas('spec', fn ($specQuery) => $specQuery->where('title', 'like', $keyword));
                });
            })
            ->orderByDesc('id');

        if ($selectedSprint) {
            $taskQuery->where('period_id', $selectedSprint->id);
        } else {
            $taskQuery->whereRaw('1 = 0');
        }

        $tasks = $taskQuery->paginate(90)->withQueryString();
        $taskItems = $tasks->getCollection();

        $activityByProject = Activity::query()
            ->where('subject_type', Project::class)
            ->whereIn('subject_id', $taskItems->pluck('id'))
            ->latest()
            ->get(['id', 'subject_id', 'description', 'event', 'created_at'])
            ->groupBy('subject_id');

        return view('project-board', [
            'tasks' => $tasks,
            'activityByProject' => $activityByProject,
            'isManager' => $isManager,
            'canReassign' => $canReassign,
            'isInternReadOnly' => (bool) $accessState['is_read_only'],
            'readOnlyReason' => $accessState['reason'],
            'isWeekendRestriction' => $isWeekendRestriction,
            'nextWeekStartDate' => $nextWeekStartDate,
            'viewMode' => $viewMode,
            'filters' => $filters,
            'projectFilters' => $projectFilters,
            'assigneeFilters' => $assigneeFilters,
            'sprints' => $sprints,
            'selectedSprint' => $selectedSprint,
            'previousSprintId' => $previousSprintId,
            'nextSprintId' => $nextSprintId,
            'availableProjects' => $availableProjects,
        ]);
    }

    public function storeTask(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'project_spec_id' => ['required', 'exists:project_specs,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'due_date' => ['required', 'date'],
            'priority' => ['nullable', Rule::in(['low', 'medium', 'high', 'critical'])],
            'sprint_id' => ['nullable', Rule::exists('periods', 'id')->where('type', Period::TYPE_SPRINT)],
        ]);

        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        if ($user->canManageAllProjects()) {
            abort(403);
        }

        $accessState = $this->internAccessState($user);
        if ($accessState['is_read_only']) {
            return back()->withErrors(['project' => $accessState['reason']])->withInput();
        }

        $isAssigned = $user->assignedProjectSpecs()
            ->where('project_specs.id', (int) $validated['project_spec_id'])
            ->exists();

        if (! $isAssigned) {
            return back()->withErrors(['project_spec_id' => 'Project tidak ter-assign untuk akun ini.'])->withInput();
        }

        $isWeekend = now()->isWeekend();
        $nextWeekStartDate = now()->startOfWeek(Carbon::MONDAY)->addWeek()->toDateString();

        if ($isWeekend && (string) $validated['due_date'] < $nextWeekStartDate) {
            return back()->withErrors([
                'due_date' => 'Saat Sabtu/Minggu hanya boleh tambah backlog untuk minggu depan (due date mulai '.$nextWeekStartDate.').',
            ])->withInput();
        }

        $targetSprint = null;

        if ($isWeekend) {
            $targetSprint = $this->resolveSprintByDate($user, (string) $validated['due_date']);
        } elseif (! empty($validated['sprint_id'])) {
            $targetSprint = Period::query()
                ->where('type', Period::TYPE_SPRINT)
                ->where('institution_id', $user->institution_id)
                ->find((int) $validated['sprint_id']);

            if (! $targetSprint) {
                return back()->withErrors(['sprint_id' => 'Sprint tidak valid untuk institusi intern.'])->withInput();
            }
        } else {
            $targetSprint = $this->resolveSprintByDate($user, (string) $validated['due_date']);
        }

        Project::create([
            'project_spec_id' => (int) $validated['project_spec_id'],
            'period_id' => $targetSprint?->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'due_date' => $validated['due_date'],
            'priority' => $validated['priority'] ?? 'medium',
            'assignee_id' => $user->id,
            'created_by' => $user->id,
            'status' => 'todo',
        ]);

        return back()->with('status', 'Task tambahan berhasil dibuat oleh intern.');
    }

    public function reassign(Request $request, Project $project): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'assignee_id' => ['required', 'exists:users,id'],
        ]);

        $user = $request->user();

        if (! $user instanceof User) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated'], 401);
            }

            abort(403);
        }

        if (! $user->isAdmin()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Forbidden'], 403);
            }

            abort(403);
        }

        if ($project->status !== 'todo') {
            $message = 'Re-assign hanya bisa dilakukan saat task masih todo.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return back()->withErrors(['assignee_id' => $message]);
        }

        $newAssignee = $this->activeInternsQuery($this->resolveProjectInstitutionId($project))
            ->find((int) $validated['assignee_id']);
        if (! $newAssignee) {
            $message = 'Assignee baru harus intern dengan periode internship aktif.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return back()->withErrors(['assignee_id' => $message]);
        }

        $oldAssigneeId = (int) $project->assignee_id;
        if ($oldAssigneeId === (int) $newAssignee->id) {
            return back()->with('status', 'Assignee sudah sama, tidak ada perubahan.');
        }

        $oldAssigneeName = $project->assignee?->name ?? 'Unknown';

        $project->update([
            'assignee_id' => (int) $newAssignee->id,
        ]);

        activity('project')
            ->performedOn($project)
            ->causedBy($user)
            ->event('reassigned')
            ->withProperties([
                'old' => ['assignee_id' => $oldAssigneeId],
                'attributes' => ['assignee_id' => (int) $newAssignee->id],
            ])
            ->log(sprintf('reassign task from %s to %s by %s', $oldAssigneeName, $newAssignee->name, $user->name));

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Assignee task berhasil diubah.',
                'data' => [
                    'id' => $project->id,
                    'assignee_id' => $project->assignee_id,
                ],
            ]);
        }

        return back()->with('status', 'Assignee task berhasil diubah.');
    }

    public function setStatus(Request $request, Project $project): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['todo', 'doing', 'done'])],
        ]);

        $user = $request->user();

        if (! $user instanceof User) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated'], 401);
            }

            abort(403);
        }

        // Sesuai requirement terbaru: intern pemilik task yang mengubah status.
        if ($project->assignee_id !== $user->id) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Forbidden'], 403);
            }

            abort(403);
        }

        if (! $user->canManageAllProjects()) {
            $accessState = $this->internAccessState($user);
            if ($accessState['is_read_only']) {
                if ($request->expectsJson()) {
                    return response()->json(['message' => $accessState['reason']], 403);
                }

                return back()->withErrors(['project' => $accessState['reason']]);
            }

            if (now()->isWeekend()) {
                $message = 'Hari Sabtu/Minggu tidak bisa ubah status kanban. Gunakan waktu ini untuk menambah backlog minggu depan.';
                if ($request->expectsJson()) {
                    return response()->json(['message' => $message], 403);
                }

                return back()->withErrors(['project' => $message]);
            }
        }

        $project->update(['status' => $validated['status']]);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Status task berhasil diperbarui.',
                'data' => [
                    'id' => $project->id,
                    'status' => $project->status,
                ],
            ]);
        }

        return back()->with('status', 'Status task berhasil diperbarui.');
    }

    public function advance(Request $request, Project $project): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        if (! $user->canManageAllProjects() && $project->assignee_id !== $user->id) {
            abort(403);
        }

        if ($project->assignee_id !== $user->id) {
            abort(403);
        }

        if (! $user->canManageAllProjects()) {
            $accessState = $this->internAccessState($user);
            if ($accessState['is_read_only']) {
                return back()->withErrors(['project' => $accessState['reason']]);
            }

            if (now()->isWeekend()) {
                return back()->withErrors([
                    'project' => 'Hari Sabtu/Minggu tidak bisa ubah status kanban. Gunakan waktu ini untuk menambah backlog minggu depan.',
                ]);
            }
        }

        $nextStatus = [
            'todo' => 'doing',
            'doing' => 'done',
            'done' => 'doing',
        ];

        $next = $nextStatus[$project->status] ?? null;

        if (! $next) {
            return back()->withErrors(['project' => 'Status project sudah final (done).']);
        }

        $project->update(['status' => $next]);

        return back()->with('status', 'Status project berhasil diubah ke '.$next.'.');
    }

    public function addComment(Request $request, Project $project): RedirectResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        if (! $user->canManageAllProjects() && $project->assignee_id !== $user->id) {
            abort(403);
        }

        if (! $user->canManageAllProjects()) {
            $accessState = $this->internAccessState($user);
            if ($accessState['is_read_only']) {
                return back()->withErrors(['project' => $accessState['reason']]);
            }
        }

        $project->comments()->create([
            'body' => $validated['body'],
            'user_id' => $user->id,
        ]);

        return back()->with('status', 'Komentar berhasil ditambahkan.');
    }

    /**
     * @return array{is_read_only: bool, reason: ?string}
     */
    private function internAccessState(User $user): array
    {
        if (! $user->institution_id) {
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

    private function resolveSprintByDate(User $user, string $date): ?Period
    {
        return Period::query()
            ->where('type', Period::TYPE_SPRINT)
            ->where('institution_id', $user->institution_id)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->first();
    }

    private function carryOverUnfinishedTasks(Period $selectedSprint, User $actor, bool $isManager): void
    {
        $previousSprint = Period::query()
            ->where('type', Period::TYPE_SPRINT)
            ->where('institution_id', $selectedSprint->institution_id)
            ->whereDate('end_date', '<', $selectedSprint->start_date)
            ->orderByDesc('end_date')
            ->first();

        if (! $previousSprint) {
            return;
        }

        $carryQuery = Project::query()
            ->where('period_id', $previousSprint->id)
            ->whereIn('status', ['todo', 'doing']);

        if (! $isManager) {
            $carryQuery->where('assignee_id', $actor->id);
        }

        $taskIds = $carryQuery->pluck('id')->map(static fn (int $id): int => (int) $id)->all();

        if ($taskIds === []) {
            return;
        }

        $now = now()->toDateTimeString();
        $note = sprintf(
            'Sprint pindah: %s -> %s karena task belum selesai.',
            $previousSprint->name,
            $selectedSprint->name,
        );

        DB::transaction(function () use ($taskIds, $selectedSprint, $note, $actor, $now): void {
            Project::query()
                ->whereIn('id', $taskIds)
                ->update([
                    'period_id' => $selectedSprint->id,
                    'updated_at' => $now,
                ]);

            $commentRows = [];
            foreach ($taskIds as $taskId) {
                $commentRows[] = [
                    'body' => $note,
                    'user_id' => $actor->id,
                    'commentable_id' => $taskId,
                    'commentable_type' => Project::class,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table('comments')->insert($commentRows);
        });
    }

    private function resolveProjectInstitutionId(Project $project): ?int
    {
        $project->loadMissing([
            'sprint:id,institution_id',
            'assignee:id,name,institution_id',
            'spec.assignedInterns:id,institution_id',
        ]);

        $institutionId = $project->sprint?->institution_id ?? $project->assignee?->institution_id;
        if ($institutionId) {
            return (int) $institutionId;
        }

        $institutionIds = $project->spec?->assignedInterns
            ?->pluck('institution_id')
            ->filter()
            ->map(static fn (int $id): int => (int) $id)
            ->unique()
            ->values();

        if ($institutionIds && $institutionIds->count() === 1) {
            return (int) $institutionIds->first();
        }

        return null;
    }

    private function activeInternsQuery(?int $institutionId = null)
    {
        $activeInstitutionIds = $this->activeInternInstitutionIds();

        $query = User::query()->role('Intern');

        if ($activeInstitutionIds === []) {
            return $query->whereRaw('1 = 0');
        }

        $query->whereIn('institution_id', $activeInstitutionIds);

        if ($institutionId) {
            $query->where('institution_id', $institutionId);
        }

        return $query;
    }

    /**
     * @return array<int, int>
     */
    private function activeInternInstitutionIds(): array
    {
        $today = now()->toDateString();

        return Period::query()
            ->where('type', Period::TYPE_INTERNSHIP)
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->pluck('institution_id')
            ->filter()
            ->map(static fn (int $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
