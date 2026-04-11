<?php

namespace App\Http\Controllers;

use App\Http\Requests\LogbookRequest;
use App\Http\Resources\LogbookResource;
use App\Models\Logbook;
use App\Models\Period;
use Illuminate\Http\JsonResponse;

class LogbookController extends Controller
{
    public function store(LogbookRequest $request): LogbookResource|JsonResponse
    {
        $data = $request->validated();
        $reportDate = $data['report_date'];

        $activePeriod = Period::query()
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
            'user_id' => $request->user()->id,
            'period_id' => $activePeriod->id,
            'report_date' => $reportDate,
            'done_tasks' => $data['done_tasks'],
            'next_tasks' => $data['next_tasks'],
            'status' => 'draft',
        ]);

        if ($request->hasFile('appendix')) {
            $logbook->addMediaFromRequest('appendix')->toMediaCollection('appendix');
        }

        return new LogbookResource($logbook->load(['period', 'user', 'media']));
    }
}
