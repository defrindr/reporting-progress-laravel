<?php

namespace App\Http\Controllers\Intern;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectSpec;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $taskDate = $request->string('task_date')->toString();

        $assignedSpecs = $isManager
            ? ProjectSpec::query()->with('assignedInterns:id,name')->latest()->get()
            : $user->assignedProjectSpecs()->latest('project_specs.id')->get();

        $tasks = Project::query()
            ->with(['spec:id,title', 'assignee:id,name', 'creator:id,name', 'comments.user:id,name'])
            ->when(! $isManager, fn ($query) => $query->where('assignee_id', $user->id))
            ->when($taskDate, fn ($query) => $query->whereDate('created_at', $taskDate))
            ->orderByDesc('id')
            ->get();

        $activityByProject = Activity::query()
            ->where('subject_type', Project::class)
            ->whereIn('subject_id', $tasks->pluck('id'))
            ->latest()
            ->get(['id', 'subject_id', 'description', 'event', 'created_at'])
            ->groupBy('subject_id');

        return view('project-board', [
            'tasks' => $tasks,
            'assignedSpecs' => $assignedSpecs,
            'activityByProject' => $activityByProject,
            'isManager' => $isManager,
            'taskDate' => $taskDate,
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

    public function setStatus(Request $request, Project $project): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['todo', 'doing', 'done'])],
        ]);

        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        // Sesuai requirement terbaru: intern pemilik task yang mengubah status.
        if ($project->assignee_id !== $user->id) {
            abort(403);
        }

        $project->update(['status' => $validated['status']]);

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
