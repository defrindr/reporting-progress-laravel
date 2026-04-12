<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectSpec;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminProjectController extends Controller
{
    public function index(Request $request): View
    {
        $taskDate = $request->string('task_date')->toString();

        return view('admin.projects.index', [
            'specs' => ProjectSpec::query()
                ->with('assignedInterns:id,name')
                ->latest()
                ->paginate(12, ['*'], 'spec_page')
                ->withQueryString(),
            'interns' => User::query()->role('Intern')->orderBy('name')->get(['id', 'name']),
            'tasks' => Project::query()
                ->with(['spec:id,title', 'assignee:id,name', 'creator:id,name'])
                ->when($taskDate, fn ($query) => $query->whereDate('created_at', $taskDate))
                ->latest()
                ->paginate(25, ['*'], 'task_page')
                ->withQueryString(),
            'taskDate' => $taskDate,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'specification' => ['required', 'string'],
            'intern_ids' => ['required', 'array', 'min:1'],
            'intern_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $spec = ProjectSpec::create([
            'title' => $validated['title'],
            'specification' => $validated['specification'],
            'created_by' => $request->user()->id,
        ]);

        $spec->assignedInterns()->sync($validated['intern_ids']);

        return back()->with('status', 'Project spec berhasil ditambahkan.');
    }

    public function update(Request $request, ProjectSpec $projectSpec): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'specification' => ['required', 'string'],
            'intern_ids' => ['required', 'array', 'min:1'],
            'intern_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $projectSpec->update([
            'title' => $validated['title'],
            'specification' => $validated['specification'],
        ]);

        $projectSpec->assignedInterns()->sync($validated['intern_ids']);

        return back()->with('status', 'Project spec berhasil diubah.');
    }

    public function destroy(ProjectSpec $projectSpec): RedirectResponse
    {
        $projectSpec->delete();

        return back()->with('status', 'Project spec berhasil dihapus.');
    }
}
