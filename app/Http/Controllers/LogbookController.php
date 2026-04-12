<?php

namespace App\Http\Controllers;

use App\Http\Requests\LogbookRequest;
use App\Http\Resources\LogbookResource;
use App\Models\Logbook;
use App\Models\Period;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LogbookController extends Controller
{
    public function index(Request $request)
    {
        $query = Logbook::query()->with(['user', 'period']);

        if ($request->filled('period_id')) {
            $query->where('period_id', $request->integer('period_id'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        return LogbookResource::collection($query->latest('report_date')->paginate(15));
    }

    public function store(LogbookRequest $request): JsonResponse
    {
        $data = $request->validated();
        $reportDate = $data['report_date'];
        $user = $request->user();

        if (! $user->institution_id) {
            return response()->json(['message' => 'User must be linked to an institution'], 422);
        }

        $activePeriod = Period::query()
            ->where('institution_id', $user->institution_id)
            ->whereDate('start_date', '<=', $reportDate)
            ->whereDate('end_date', '>=', $reportDate)
            ->first();

        if (! $activePeriod) {
            return response()->json(['message' => 'No active period found for this report date'], 422);
        }

        if (in_array($reportDate, $activePeriod->holidays ?? [], true)) {
            return response()->json(['message' => 'Cannot submit reports on holidays'], 422);
        }

        $logbook = Logbook::create([
            'user_id' => $user->id,
            'period_id' => $activePeriod->id,
            'report_date' => $reportDate,
            'done_tasks' => $data['done_tasks'],
            'next_tasks' => $data['next_tasks'],
            'appendix_link' => $data['appendix_link'] ?? null,
            'status' => 'draft',
        ]);

        return (new LogbookResource($logbook->load(['period', 'user'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Logbook $logbook): LogbookResource
    {
        return new LogbookResource($logbook->load(['period', 'user']));
    }

    public function update(LogbookRequest $request, Logbook $logbook): LogbookResource|JsonResponse
    {
        $data = $request->validated();
        $reportDate = $data['report_date'];
        $user = $request->user();

        if (! $user->institution_id) {
            return response()->json(['message' => 'User must be linked to an institution'], 422);
        }

        $activePeriod = Period::query()
            ->where('institution_id', $user->institution_id)
            ->whereDate('start_date', '<=', $reportDate)
            ->whereDate('end_date', '>=', $reportDate)
            ->first();

        if (! $activePeriod) {
            return response()->json(['message' => 'No active period found for this report date'], 422);
        }

        if (in_array($reportDate, $activePeriod->holidays ?? [], true)) {
            return response()->json(['message' => 'Cannot submit reports on holidays'], 422);
        }

        $logbook->update([
            'period_id' => $activePeriod->id,
            'report_date' => $reportDate,
            'done_tasks' => $data['done_tasks'],
            'next_tasks' => $data['next_tasks'],
            'appendix_link' => $data['appendix_link'] ?? null,
        ]);

        return new LogbookResource($logbook->load(['period', 'user']));
    }

    public function destroy(Logbook $logbook): JsonResponse
    {
        $logbook->delete();

        return response()->json(status: 204);
    }
}
