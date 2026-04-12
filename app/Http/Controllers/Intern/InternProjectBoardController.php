<?php

namespace App\Http\Controllers\Intern;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;

class InternProjectBoardController extends Controller
{
    public function index(): View
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            abort(403);
        }

        $projects = Project::query()
            ->with(['assignee:id,name', 'comments.user:id,name'])
            ->when(
                ! $user->canManageAllProjects(),
                fn ($query) => $query->where('assignee_id', $user->id)
            )
            ->orderByDesc('id')
            ->get();

        $activityByProject = Activity::query()
            ->where('subject_type', Project::class)
            ->whereIn('subject_id', $projects->pluck('id'))
            ->latest()
            ->get(['id', 'subject_id', 'description', 'event', 'created_at'])
            ->groupBy('subject_id');

        return view('project-board', [
            'projects' => $projects,
            'activityByProject' => $activityByProject,
        ]);
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

        $nextStatus = [
            'todo' => 'doing',
            'doing' => 'done',
            'done' => null,
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
