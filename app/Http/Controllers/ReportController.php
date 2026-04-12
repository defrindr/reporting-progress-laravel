<?php

namespace App\Http\Controllers;

use App\Models\Logbook;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period_id' => ['required', 'exists:periods,id'],
            'institution_id' => ['required', 'exists:institutions,id'],
        ]);

        $approvedLogbooks = Logbook::query()
            ->where('period_id', $validated['period_id'])
            ->where('status', 'approved')
            ->whereHas('user', function ($query) use ($validated): void {
                $query->where('institution_id', $validated['institution_id']);
            })
            ->count();

        $doneProjects = Project::query()
            ->where('status', 'done')
            ->whereHas('assignee', function ($query) use ($validated): void {
                $query->where('institution_id', $validated['institution_id']);
            })
            ->count();

        return response()->json([
            'period_id' => (int) $validated['period_id'],
            'institution_id' => (int) $validated['institution_id'],
            'total_logbooks_approved' => $approvedLogbooks,
            'total_projects_done' => $doneProjects,
        ]);
    }
}
