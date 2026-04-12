<?php

namespace App\Http\Controllers;

use App\Models\Period;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PeriodController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Period::query()->with('institution:id,name,type')->orderByDesc('start_date')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'institution_id' => ['required', Rule::exists('institutions', 'id')->where('type', 'university')],
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'holidays' => ['nullable', 'array'],
            'holidays.*' => ['date'],
        ]);

        return response()->json(['data' => Period::create($validated)], 201);
    }

    public function update(Request $request, Period $period): JsonResponse
    {
        $validated = $request->validate([
            'institution_id' => ['sometimes', Rule::exists('institutions', 'id')->where('type', 'university')],
            'name' => ['sometimes', 'string', 'max:255'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date', 'after_or_equal:start_date'],
            'holidays' => ['nullable', 'array'],
            'holidays.*' => ['date'],
        ]);

        $period->update($validated);

        return response()->json(['data' => $period]);
    }

    public function destroy(Period $period): JsonResponse
    {
        $period->delete();

        return response()->json(status: 204);
    }
}
