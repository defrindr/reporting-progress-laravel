<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $projects = Project::query()
            ->with(['spec:id,title', 'assignee:id,name,institution_id', 'creator:id,name', 'sprint:id,name,start_date,end_date', 'comments.user:id,name'])
            ->when(
                $request->filled('institution_id'),
                fn (Builder $query): Builder => $query->whereHas(
                    'assignee',
                    fn (Builder $userQuery): Builder => $userQuery->where('institution_id', $request->integer('institution_id'))
                )
            )
            ->when(
                $request->filled('period_id'),
                fn (Builder $query): Builder => $query->where('period_id', $request->integer('period_id'))
            )
            ->orderByDesc('id')
            ->get();

        return response()->json(['data' => $projects]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_spec_id' => ['nullable', 'exists:project_specs,id'],
            'period_id' => ['nullable', Rule::exists('periods', 'id')->where('type', 'sprint')],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'due_date' => ['nullable', 'date'],
            'priority' => ['nullable', Rule::in(['low', 'medium', 'high', 'critical'])],
            'assignee_id' => ['nullable', 'exists:users,id'],
            'status' => ['nullable', Rule::in(['todo', 'doing', 'done'])],
        ]);

        $assigneeId = $validated['assignee_id'] ?? $request->user()->id;

        $project = Project::create([
            'project_spec_id' => $validated['project_spec_id'] ?? null,
            'period_id' => $validated['period_id'] ?? null,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'due_date' => $validated['due_date'] ?? null,
            'priority' => $validated['priority'] ?? 'medium',
            'assignee_id' => $assigneeId,
            'created_by' => $request->user()->id,
            'status' => $validated['status'] ?? 'todo',
        ]);

        return response()->json(['data' => $project->load(['spec:id,title', 'assignee:id,name,institution_id', 'creator:id,name', 'sprint:id,name,start_date,end_date'])], 201);
    }

    public function show(Project $project): JsonResponse
    {
        return response()->json([
            'data' => $project->load(['spec:id,title', 'assignee:id,name,institution_id', 'creator:id,name', 'sprint:id,name,start_date,end_date', 'comments.user:id,name']),
        ]);
    }

    public function updateStatus(Request $request, Project $project): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['todo', 'doing', 'done'])],
        ]);

        $project->update(['status' => $validated['status']]);

        return response()->json([
            'message' => 'Project status updated',
            'data' => $project,
        ]);
    }

    public function update(Request $request, Project $project): JsonResponse
    {
        $validated = $request->validate([
            'project_spec_id' => ['sometimes', 'nullable', 'exists:project_specs,id'],
            'period_id' => ['sometimes', 'nullable', Rule::exists('periods', 'id')->where('type', 'sprint')],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'priority' => ['sometimes', Rule::in(['low', 'medium', 'high', 'critical'])],
            'assignee_id' => ['sometimes', 'exists:users,id'],
            'status' => ['sometimes', Rule::in(['todo', 'doing', 'done'])],
        ]);

        $project->update($validated);

        return response()->json(['data' => $project->load(['spec:id,title', 'assignee:id,name,institution_id', 'creator:id,name', 'sprint:id,name,start_date,end_date'])]);
    }

    public function addComment(Request $request, Project $project): JsonResponse
    {
        if (! $request->user()) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $validated = $request->validate([
            'body' => ['required', 'string'],
        ]);

        $comment = $project->comments()->create([
            'body' => $validated['body'],
            'user_id' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Comment added',
            'data' => $comment->load('user:id,name'),
        ], 201);
    }

    public function activity(Project $project): JsonResponse
    {
        $activities = $project->activities()
            ->latest()
            ->get(['id', 'description', 'event', 'properties', 'created_at']);

        return response()->json(['data' => $activities]);
    }

    public function destroy(Project $project): JsonResponse
    {
        $project->delete();

        return response()->json(status: 204);
    }
}
