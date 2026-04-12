<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Period;
use App\Models\Project;
use App\Models\ProjectSpec;
use App\Models\User;
use App\Support\SprintWindow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminProjectController extends Controller
{
    public function index(): View
    {
        return view('admin.projects.index', [
            'specs' => ProjectSpec::query()
                ->with(['assignedInterns:id,name', 'creator:id,name'])
                ->withCount('backlogs')
                ->latest()
                ->paginate(12, ['*'], 'spec_page')
                ->withQueryString(),
            'interns' => User::query()->role('Intern')->orderBy('name')->get(['id', 'name']),
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
        $scope = $request->string('scope')->toString();
        if (! in_array($scope, ['backlog', 'sprint', 'all'], true)) {
            $scope = 'backlog';
        }

        $sprintId = $request->integer('sprint_id');

        $backlogQuery = Project::query()
            ->with(['assignee:id,name', 'creator:id,name', 'sprint:id,name,start_date,end_date'])
            ->where('project_spec_id', $projectSpec->id);

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
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $activationCandidates = Project::query()
            ->where('project_spec_id', $projectSpec->id)
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
        if ($activationSprint) {
            $activeBacklogIds = Project::query()
                ->where('project_spec_id', $projectSpec->id)
                ->where('period_id', $activationSprint->id)
                ->pluck('id')
                ->map(static fn (int $id): int => (int) $id)
                ->all();
        }

        $periodsQuery = Period::query()
            ->where('type', Period::TYPE_SPRINT)
            ->with('institution:id,name')
            ->orderByDesc('start_date');

        if ($activationInstitutionId) {
            $periodsQuery->where('institution_id', $activationInstitutionId);
        }

        return view('admin.projects.show', [
            'project' => $projectSpec->load('creator:id,name'),
            'interns' => User::query()->role('Intern')->orderBy('name')->get(['id', 'name']),
            'periods' => $periodsQuery->get(),
            'backlogs' => $backlogs,
            'activationCandidates' => $activationCandidates,
            'activationSprint' => $activationSprint,
            'activationError' => $activationError,
            'activeBacklogIds' => $activeBacklogIds,
            'scope' => $scope,
            'sprintId' => $sprintId,
        ]);
    }

    public function storeBacklog(Request $request, ProjectSpec $projectSpec): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assignee_id' => ['required', 'exists:users,id'],
            'due_date' => ['nullable', 'date'],
            'priority' => ['required', Rule::in(['low', 'medium', 'high', 'critical'])],
            'status' => ['required', Rule::in(['todo', 'doing', 'done'])],
        ]);

        Project::create([
            'project_spec_id' => $projectSpec->id,
            'period_id' => null,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'assignee_id' => (int) $validated['assignee_id'],
            'due_date' => $validated['due_date'] ?? null,
            'priority' => $validated['priority'],
            'status' => $validated['status'],
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
            'assignee_id' => ['required', 'exists:users,id'],
            'due_date' => ['nullable', 'date'],
            'priority' => ['required', Rule::in(['low', 'medium', 'high', 'critical'])],
            'status' => ['required', Rule::in(['todo', 'doing', 'done'])],
        ]);

        $backlog->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'assignee_id' => (int) $validated['assignee_id'],
            'due_date' => $validated['due_date'] ?? null,
            'priority' => $validated['priority'],
            'status' => $validated['status'],
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
        ]);

        $selectedBacklogIds = Project::query()
            ->where('project_spec_id', $projectSpec->id)
            ->whereIn('id', $validated['backlog_ids'])
            ->pluck('id')
            ->map(static fn (int $id): int => (int) $id)
            ->all();

        if ($selectedBacklogIds === []) {
            return back()->withErrors(['backlog_ids' => 'Pilih backlog yang valid untuk project ini.']);
        }

        [$institutionId, $activationError] = $this->resolveActivationInstitutionId($projectSpec, $selectedBacklogIds);
        if ($activationError) {
            return back()->withErrors(['backlog_ids' => $activationError]);
        }

        if (! $institutionId) {
            return back()->withErrors(['backlog_ids' => 'Tidak bisa menentukan institusi untuk sprint otomatis.']);
        }

        [$targetSprint, $createdNewSprint] = $this->resolveTargetSprint($institutionId, true);
        if (! $targetSprint) {
            return back()->withErrors(['backlog_ids' => 'Gagal menentukan sprint untuk aktivasi backlog.']);
        }

        DB::transaction(function () use ($projectSpec, $targetSprint, $selectedBacklogIds): void {
            Project::query()
                ->where('project_spec_id', $projectSpec->id)
                ->where('period_id', $targetSprint->id)
                ->whereNotIn('id', $selectedBacklogIds)
                ->update(['period_id' => null]);

            Project::query()
                ->where('project_spec_id', $projectSpec->id)
                ->whereIn('id', $selectedBacklogIds)
                ->update(['period_id' => $targetSprint->id]);
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
     * @return array{0: ?int, 1: ?string}
     */
    private function resolveActivationInstitutionId(ProjectSpec $projectSpec, array $selectedBacklogIds): array
    {
        $institutionIds = collect();

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

        $assignedInstitutionIds = $projectSpec->assignedInterns()
            ->whereNotNull('users.institution_id')
            ->pluck('users.institution_id')
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
     * @return array{0: ?Period, 1: bool}
     */
    private function resolveTargetSprint(int $institutionId, bool $createIfMissing): array
    {
        [$startDate, $endDate] = SprintWindow::resolveRange(Carbon::now(), true);

        $activeSprint = Period::query()
            ->where('type', Period::TYPE_SPRINT)
            ->where('institution_id', $institutionId)
            ->whereDate('start_date', $startDate->toDateString())
            ->whereDate('end_date', $endDate->toDateString())
            ->orderByDesc('start_date')
            ->first();

        if ($activeSprint) {
            return [$activeSprint, false];
        }

        if (! $createIfMissing) {
            return [null, false];
        }

        $newSprint = Period::query()->firstOrCreate(
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

        return [$newSprint, $newSprint->wasRecentlyCreated];
    }
}
