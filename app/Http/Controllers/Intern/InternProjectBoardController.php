<?php

namespace App\Http\Controllers\Intern;

use App\Http\Controllers\Controller;
use App\Models\Period;
use App\Models\Project;
use App\Models\ProjectSpec;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
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
        $sprints = Period::query()
            ->with('institution:id,name')
            ->when(! $isManager, fn ($query) => $query->where('institution_id', $user->institution_id))
            ->orderByDesc('start_date')
            ->get();

        $selectedSprintId = $request->integer('sprint_id');
        $selectedSprint = $sprints->firstWhere('id', $selectedSprintId);

        if (! $selectedSprint) {
            $selectedSprint = $sprints->first(function (Period $period): bool {
                $today = now()->toDateString();
                $startDate = Carbon::parse((string) $period->start_date)->toDateString();
                $endDate = Carbon::parse((string) $period->end_date)->toDateString();

                return $today >= $startDate && $today <= $endDate;
            }) ?? $sprints->first();
        }

        $assignedSpecs = $isManager
            ? ProjectSpec::query()->with('assignedInterns:id,name')->latest()->get()
            : $user->assignedProjectSpecs()->latest('project_specs.id')->get();

        $taskQuery = Project::query()
            ->with(['spec:id,title', 'assignee:id,name', 'creator:id,name', 'comments.user:id,name'])
            ->when(! $isManager, fn ($query) => $query->where('assignee_id', $user->id))
            ->orderByDesc('id');

        if ($selectedSprint) {
            $taskQuery
                ->whereDate('created_at', '>=', $selectedSprint->start_date)
                ->whereDate('created_at', '<=', $selectedSprint->end_date);
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
            'assignedSpecs' => $assignedSpecs,
            'activityByProject' => $activityByProject,
            'isManager' => $isManager,
            'sprints' => $sprints,
            'selectedSprint' => $selectedSprint,
        ]);
    }

    public function storeTask(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'project_spec_id' => ['required', 'exists:project_specs,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        if ($user->canManageAllProjects()) {
            abort(403);
        }

        $isAssigned = $user->assignedProjectSpecs()
            ->where('project_specs.id', (int) $validated['project_spec_id'])
            ->exists();

        if (! $isAssigned) {
            abort(403);
        }

        Project::create([
            'project_spec_id' => (int) $validated['project_spec_id'],
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'assignee_id' => $user->id,
            'created_by' => $user->id,
            'status' => 'todo',
        ]);

        return back()->with('status', 'Task berhasil dibuat dari spec yang di-assign.');
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

        $project->comments()->create([
            'body' => $validated['body'],
            'user_id' => $user->id,
        ]);

        return back()->with('status', 'Komentar berhasil ditambahkan.');
    }
}
