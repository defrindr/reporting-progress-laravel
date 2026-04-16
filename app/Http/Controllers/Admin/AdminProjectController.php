<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Period;
use App\Models\Project;
use App\Models\ProjectSpec;
use App\Models\User;
use App\Support\SprintPeriodResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminProjectController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'intern_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'sort' => ['nullable', 'string', 'in:title,created_at,backlogs_count'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
        ]);

        $search = trim((string) ($validated['q'] ?? ''));
        $internId = isset($validated['intern_id']) ? (int) $validated['intern_id'] : null;
        $sort = (string) ($validated['sort'] ?? 'created_at');
        $direction = (string) ($validated['direction'] ?? 'desc');

        $specsQuery = ProjectSpec::query()
            ->with(['assignedInterns:id,name', 'creator:id,name'])
            ->withCount('backlogs');

        if ($search !== '') {
            $specsQuery->where(function ($query) use ($search): void {
                $query
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('specification', 'like', "%{$search}%");
            });
        }

        if ($internId) {
            $specsQuery->whereHas('assignedInterns', static function ($query) use ($internId): void {
                $query->where('users.id', $internId);
            });
        }

        return view('admin.projects.index', [
            'specs' => $specsQuery
                ->orderBy($sort, $direction)
                ->orderByDesc('id')
                ->paginate(12, ['*'], 'spec_page')
                ->withQueryString(),
            'interns' => User::query()->role('Intern')->orderBy('name')->get(['id', 'name']),
            'filters' => [
                'q' => $search,
                'intern_id' => $internId,
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'specification' => ['nullable', 'string'],
            'intern_ids' => ['nullable', 'array'],
            'intern_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $name = trim((string) ($validated['name'] ?? $validated['title'] ?? ''));
        $description = trim((string) ($validated['description'] ?? $validated['specification'] ?? ''));

        if ($name === '' || $description === '') {
            return back()->withErrors([
                'name' => 'Nama project dan deskripsi wajib diisi.',
            ])->withInput();
        }

        $spec = ProjectSpec::create([
            'title' => $name,
            'specification' => $description,
            'created_by' => $request->user()->id,
        ]);

        if (isset($validated['intern_ids'])) {
            $spec->assignedInterns()->sync($validated['intern_ids']);
        }

        return redirect()->route('admin.projects.show', $spec)->with('status', 'Project berhasil ditambahkan.');
    }

    public function show(Request $request, ProjectSpec $projectSpec): View
    {
        $validated = $request->validate([
            'scope' => ['nullable', 'string', 'in:backlog,sprint,all'],
            'sprint_id' => ['nullable', 'integer', Rule::exists('periods', 'id')],
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:todo,doing,done'],
            'priority' => ['nullable', 'string', 'in:low,medium,high,critical'],
            'assignee_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'sort' => ['nullable', 'string', 'in:title,due_date,priority,status,created_at'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
        ]);

        $activeProjectInterns = $this->projectActiveInterns($projectSpec);

        $scope = (string) ($validated['scope'] ?? 'backlog');
        if ( ! in_array($scope, ['backlog', 'sprint', 'all'], true)) {
            $scope = 'backlog';
        }

        $sprintId = isset($validated['sprint_id']) ? (int) $validated['sprint_id'] : 0;
        $search = trim((string) ($validated['q'] ?? ''));
        $status = (string) ($validated['status'] ?? '');
        $priority = (string) ($validated['priority'] ?? '');
        $assigneeId = isset($validated['assignee_id']) ? (int) $validated['assignee_id'] : null;
        $sort = (string) ($validated['sort'] ?? 'created_at');
        $direction = (string) ($validated['direction'] ?? 'desc');

        $backlogQuery = Project::query()
            ->with(['assignee:id,name', 'creator:id,name', 'sprint:id,name,start_date,end_date'])
            ->where('project_spec_id', $projectSpec->id);

        if ($search !== '') {
            $backlogQuery->where(function ($query) use ($search): void {
                $query
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($status !== '') {
            $backlogQuery->where('status', $status);
        }

        if ($priority !== '') {
            $backlogQuery->where('priority', $priority);
        }

        if ($assigneeId) {
            $backlogQuery->where('assignee_id', $assigneeId);
        }

        if ($scope === 'backlog') {
            $backlogQuery->whereNull('period_id');
        }

        if ($scope === 'sprint') {
            if ($sprintId > 0) {
                $backlogQuery->where('period_id', $sprintId);
            } else {
                $backlogQuery->whereNotNull('period_id');
            }
        }

        $backlogs = $backlogQuery
            ->orderBy($sort, $direction)
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $activationCandidates = Project::query()
            ->where('project_spec_id', $projectSpec->id)
            ->whereNull('period_id')
            ->with(['assignee:id,name', 'sprint:id,name'])
            ->orderByDesc('id')
            ->get(['id', 'project_spec_id', 'period_id', 'title', 'assignee_id', 'priority', 'status']);

        [$activationInstitutionId, $activationError] = $this->resolveActivationInstitutionId(
            projectSpec: $projectSpec,
            selectedBacklogIds: $activationCandidates->pluck('id')->map(static fn (int $id): int => (int) $id)->all(),
        );

        [$activationSprint] = $activationInstitutionId
            ? $this->resolveTargetSprint($activationInstitutionId, false)
            : [null, false];

        $activeBacklogIds = [];

        $periodsQuery = Period::query()
            ->where('type', Period::TYPE_SPRINT)
            ->with('institution:id,name')
            ->orderByDesc('start_date');

        if ($activationInstitutionId) {
            $periodsQuery->where('institution_id', $activationInstitutionId);
        }

        return view('admin.projects.show', [
            'project' => $projectSpec->load('creator:id,name'),
            'interns' => $activeProjectInterns,
            'periods' => $periodsQuery->get(),
            'backlogs' => $backlogs,
            'activationCandidates' => $activationCandidates,
            'activationSprint' => $activationSprint,
            'activationError' => $activationError,
            'activeBacklogIds' => $activeBacklogIds,
            'scope' => $scope,
            'sprintId' => $sprintId,
            'filters' => [
                'q' => $search,
                'status' => $status,
                'priority' => $priority,
                'assignee_id' => $assigneeId,
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
    }

    public function storeBacklog(Request $request, ProjectSpec $projectSpec): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'due_date' => ['nullable', 'date'],
            'priority' => ['required', Rule::in(['low', 'medium', 'high', 'critical'])],
        ]);

        Project::create([
            'project_spec_id' => $projectSpec->id,
            'period_id' => null,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'assignee_id' => null,
            'due_date' => $validated['due_date'] ?? null,
            'priority' => $validated['priority'],
            'status' => 'todo',
            'created_by' => $request->user()->id,
        ]);

        return back()->with('status', 'Backlog berhasil ditambahkan.');
    }

    public function updateBacklog(Request $request, ProjectSpec $projectSpec, Project $backlog): RedirectResponse
    {
        if ($backlog->project_spec_id !== $projectSpec->id) {
            abort(404);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'due_date' => ['nullable', 'date'],
            'priority' => ['required', Rule::in(['low', 'medium', 'high', 'critical'])],
            'status' => ['required', Rule::in(['todo', 'doing', 'done'])],
        ]);

        $backlog->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'due_date' => $validated['due_date'] ?? null,
            'priority' => $validated['priority'],
            'status' => $backlog->period_id ? $validated['status'] : 'todo',
        ]);

        return back()->with('status', 'Backlog berhasil diubah.');
    }

    public function destroyBacklog(ProjectSpec $projectSpec, Project $backlog): RedirectResponse
    {
        if ($backlog->project_spec_id !== $projectSpec->id) {
            abort(404);
        }

        $backlog->delete();

        return back()->with('status', 'Backlog berhasil dihapus.');
    }

    public function activateSprint(Request $request, ProjectSpec $projectSpec): RedirectResponse
    {
        $validated = $request->validate([
            'backlog_ids' => ['required', 'array', 'min:1'],
            'backlog_ids.*' => ['integer', 'exists:projects,id'],
            'assignees' => ['nullable', 'array'],
            'assignees.*' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $selectedBacklogIds = Project::query()
            ->where('project_spec_id', $projectSpec->id)
            ->whereNull('period_id')
            ->whereIn('id', $validated['backlog_ids'])
            ->pluck('id')
            ->map(static fn (int $id): int => (int) $id)
            ->all();

        if ($selectedBacklogIds === []) {
            return back()->withErrors(['backlog_ids' => 'Pilih backlog yang valid untuk project ini.']);
        }

        $assigneeMap = collect($validated['assignees'] ?? [])
            ->mapWithKeys(static fn ($assigneeId, $backlogId): array => [(int) $backlogId => $assigneeId ? (int) $assigneeId : null]);

        $missingAssigneeBacklogIds = collect($selectedBacklogIds)
            ->filter(static fn (int $id): bool => ! isset($assigneeMap[$id]) || ! $assigneeMap[$id])
            ->values()
            ->all();

        if ($missingAssigneeBacklogIds !== []) {
            return back()->withErrors([
                'assignees' => 'Setiap backlog yang dipilih wajib punya assignee saat dimasukkan ke sprint.',
            ]);
        }

        $eligibleInternIds = $this->projectActiveInterns($projectSpec)
            ->pluck('id')
            ->map(static fn (int $id): int => (int) $id)
            ->all();

        $selectedAssigneeIds = collect($selectedBacklogIds)
            ->map(static fn (int $id): ?int => $assigneeMap[$id] ?? null)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $invalidAssigneeIds = collect($selectedAssigneeIds)
            ->diff($eligibleInternIds)
            ->values()
            ->all();

        if ($invalidAssigneeIds !== []) {
            return back()->withErrors([
                'assignees' => 'Assignee harus berasal dari intern project dengan periode internship yang sedang aktif.',
            ]);
        }

        [$institutionId, $activationError] = $this->resolveActivationInstitutionId($projectSpec, $selectedBacklogIds, $selectedAssigneeIds);
        if ($activationError) {
            return back()->withErrors(['backlog_ids' => $activationError]);
        }

        if ( ! $institutionId) {
            return back()->withErrors(['backlog_ids' => 'Tidak bisa menentukan institusi untuk sprint otomatis.']);
        }

        [$targetSprint, $createdNewSprint] = $this->resolveTargetSprint($institutionId, true);
        if ( ! $targetSprint) {
            return back()->withErrors(['backlog_ids' => 'Gagal menentukan sprint untuk aktivasi backlog.']);
        }

        DB::transaction(function () use ($projectSpec, $targetSprint, $selectedBacklogIds, $assigneeMap): void {
            foreach ($selectedBacklogIds as $backlogId) {
                Project::query()
                    ->where('project_spec_id', $projectSpec->id)
                    ->where('id', $backlogId)
                    ->whereNull('period_id')
                    ->update([
                        'period_id' => $targetSprint->id,
                        'assignee_id' => (int) $assigneeMap[$backlogId],
                    ]);
            }
        });

        $status = $createdNewSprint
            ? 'Sprint baru berhasil dibuat dan backlog terpilih sudah diaktifkan.'
            : 'Backlog terpilih berhasil diaktifkan ke sprint berjalan.';

        return back()->with('status', $status);
    }

    public function update(Request $request, ProjectSpec $projectSpec): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'specification' => ['nullable', 'string'],
            'intern_ids' => ['nullable', 'array'],
            'intern_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $name = trim((string) ($validated['name'] ?? $validated['title'] ?? ''));
        $description = trim((string) ($validated['description'] ?? $validated['specification'] ?? ''));

        if ($name === '' || $description === '') {
            return back()->withErrors([
                'name' => 'Nama project dan deskripsi wajib diisi.',
            ])->withInput();
        }

        $projectSpec->update([
            'title' => $name,
            'specification' => $description,
        ]);

        if (isset($validated['intern_ids'])) {
            $projectSpec->assignedInterns()->sync($validated['intern_ids']);
        }

        return back()->with('status', 'Project berhasil diubah.');
    }

    public function destroy(ProjectSpec $projectSpec): RedirectResponse
    {
        $projectSpec->delete();

        return redirect()->route('admin.projects.index')->with('status', 'Project berhasil dihapus.');
    }

    /**
     * @param  array<int, int>  $selectedBacklogIds
     * @param  array<int, int>  $selectedAssigneeIds
     *
     * @return array{0: ?int, 1: ?string}
     */
    private function resolveActivationInstitutionId(ProjectSpec $projectSpec, array $selectedBacklogIds, array $selectedAssigneeIds = []): array
    {
        $institutionIds = collect();

        if ($selectedAssigneeIds !== []) {
            $institutionIds = User::query()
                ->whereIn('id', $selectedAssigneeIds)
                ->whereNotNull('institution_id')
                ->pluck('institution_id')
                ->map(static fn (int $id): int => (int) $id)
                ->unique()
                ->values();

            if ($institutionIds->count() > 1) {
                return [null, 'Assignee backlog terpilih berasal dari lebih dari satu institusi. Aktifkan sprint untuk satu institusi yang sama.'];
            }

            if ($institutionIds->count() === 1) {
                return [(int) $institutionIds->first(), null];
            }
        }

        if ($selectedBacklogIds !== []) {
            $institutionIds = Project::query()
                ->where('project_spec_id', $projectSpec->id)
                ->whereIn('id', $selectedBacklogIds)
                ->with('assignee:id,institution_id')
                ->get()
                ->pluck('assignee.institution_id')
                ->filter()
                ->map(static fn ($id): int => (int) $id)
                ->unique()
                ->values();

            if ($institutionIds->count() > 1) {
                return [null, 'Backlog terpilih berasal dari lebih dari satu institusi. Aktifkan sprint per institusi yang sama.'];
            }
        }

        if ($institutionIds->count() === 1) {
            return [(int) $institutionIds->first(), null];
        }

        $assignedInstitutionIds = $this->projectActiveInterns($projectSpec)
            ->pluck('institution_id')
            ->filter()
            ->map(static fn (int $id): int => (int) $id)
            ->unique()
            ->values();

        if ($assignedInstitutionIds->count() > 1) {
            return [null, 'Project ini memiliki intern dari beberapa institusi. Aktifkan sprint otomatis untuk satu institusi saja.'];
        }

        if ($assignedInstitutionIds->count() === 1) {
            return [(int) $assignedInstitutionIds->first(), null];
        }

        return [null, 'Project belum memiliki institusi intern yang bisa dipakai untuk membuat sprint otomatis.'];
    }

    /**
     * @return Collection<int, User>
     */
    private function projectActiveInterns(ProjectSpec $projectSpec): Collection
    {
        $activeInternIds = $this->activeInternIds();

        if ($activeInternIds === []) {
            return collect();
        }

        return $projectSpec->assignedInterns()
            ->role('Intern')
            ->whereIn('users.id', $activeInternIds)
            ->orderBy('users.name')
            ->get(['users.id', 'users.name', 'users.institution_id']);
    }

    /**
     * @return array<int, int>
     */
    private function activeInternIds(?int $institutionId = null): array
    {
        $today = now()->toDateString();

        return User::query()
            ->role('Intern')
            ->when($institutionId, static fn ($query) => $query->where('institution_id', $institutionId))
            ->whereHas('internshipPeriods', static function ($query) use ($today, $institutionId): void {
                $query
                    ->whereDate('start_date', '<=', $today)
                    ->whereDate('end_date', '>=', $today);

                if ($institutionId) {
                    $query->where('institution_id', $institutionId);
                }
            })
            ->pluck('id')
            ->map(static fn (int $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array{0: ?Period, 1: bool}
     */
    private function resolveTargetSprint(int $institutionId, bool $createIfMissing): array
    {
        return SprintPeriodResolver::resolveForInstitution(
            institutionId: $institutionId,
            baseDate: Carbon::now(),
            createIfMissing: $createIfMissing,
        );
    }
}
