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
            ->with(['assignee:id,name,institution_id', 'comments.user:id,name'])
            ->when(
                $request->filled('institution_id'),
                fn (Builder $query): Builder => $query->whereHas(
                    'assignee',
                    fn (Builder $userQuery): Builder => $userQuery->where('institution_id', $request->integer('institution_id'))
                )
            )
            ->orderByDesc('id')
            ->get();

        return response()->json(['data' => $projects]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assignee_id' => ['required', 'exists:users,id'],
            'status' => ['nullable', Rule::in(['todo', 'doing', 'done'])],
        ]);

        $project = Project::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'assignee_id' => $validated['assignee_id'],
            'status' => $validated['status'] ?? 'todo',
        ]);

        return response()->json(['data' => $project->load('assignee:id,name,institution_id')], 201);
    }

    public function show(Project $project): JsonResponse
    {
        return response()->json([
            'data' => $project->load(['assignee:id,name,institution_id', 'comments.user:id,name']),
        ]);
    }

    public function updateStatus(Request $request, Project $project): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['todo', 'doing', 'done'])],
        ]);

        $nextStatus = [
            'todo' => 'doing',
            'doing' => 'done',
            'done' => null,
        ];

        if ($validated['status'] !== $nextStatus[$project->status]) {
            return response()->json([
                'message' => 'Invalid status transition',
            ], 422);
        }

        $project->update(['status' => $validated['status']]);

        return response()->json([
            'message' => 'Project status updated',
            'data' => $project,
        ]);
    }

    public function update(Request $request, Project $project): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assignee_id' => ['sometimes', 'exists:users,id'],
            'status' => ['sometimes', Rule::in(['todo', 'doing', 'done'])],
        ]);

        $project->update($validated);

        return response()->json(['data' => $project->load('assignee:id,name,institution_id')]);
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
