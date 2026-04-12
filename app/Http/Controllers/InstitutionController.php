<?php

namespace App\Http\Controllers;

use App\Models\Institution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InstitutionController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Institution::query()->orderBy('name')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['university', 'vocational'])],
        ]);

        return response()->json(['data' => Institution::create($validated)], 201);
    }

    public function update(Request $request, Institution $institution): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', Rule::in(['university', 'vocational'])],
        ]);

        $institution->update($validated);

        return response()->json(['data' => $institution]);
    }

    public function destroy(Institution $institution): JsonResponse
    {
        $institution->delete();

        return response()->json(status: 204);
    }
}
