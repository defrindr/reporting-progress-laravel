<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminProjectController extends Controller
{
    public function index(): View
    {
        return view('admin.projects.index', [
            'projects' => Project::query()->with('assignee:id,name')->latest()->get(),
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assignee_id' => ['required', 'exists:users,id'],
            'status' => ['required', Rule::in(['todo', 'doing', 'done'])],
        ]);

        Project::create($validated);

        return back()->with('status', 'Project berhasil ditambahkan.');
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assignee_id' => ['required', 'exists:users,id'],
            'status' => ['required', Rule::in(['todo', 'doing', 'done'])],
        ]);

        $project->update($validated);

        return back()->with('status', 'Project berhasil diubah.');
    }

    public function destroy(Project $project): RedirectResponse
    {
        $project->delete();

        return back()->with('status', 'Project berhasil dihapus.');
    }
}
