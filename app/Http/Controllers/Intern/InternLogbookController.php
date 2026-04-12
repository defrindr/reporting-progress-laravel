<?php

namespace App\Http\Controllers\Intern;

use App\Http\Controllers\Controller;
use App\Http\Requests\LogbookRequest;
use App\Models\Logbook;
use App\Models\Period;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class InternLogbookController extends Controller
{
    public function index(): View
    {
        $user = Auth::user();

        return view('logbook', [
            'logbooks' => Logbook::query()
                ->where('user_id', $user->id)
                ->with('period:id,name')
                ->latest('report_date')
                ->get(),
        ]);
    }

    public function store(LogbookRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $reportDate = $data['report_date'];
        $user = $request->user();

        if (! $user->institution_id) {
            return back()->withErrors(['report_date' => 'Akun intern harus terhubung ke institusi.'])->withInput();
        }

        $activePeriod = Period::query()
            ->where('institution_id', $user->institution_id)
            ->where('type', Period::TYPE_INTERNSHIP)
            ->whereDate('start_date', '<=', $reportDate)
            ->whereDate('end_date', '>=', $reportDate)
            ->first();

        if (! $activePeriod) {
            return back()->withErrors(['report_date' => 'Tidak ada periode aktif untuk tanggal ini.'])->withInput();
        }

        if (in_array($reportDate, $activePeriod->holidays ?? [], true)) {
            return back()->withErrors(['report_date' => 'Cannot submit reports on holidays'])->withInput();
        }

        $logbook = Logbook::create([
            'user_id' => $user->id,
            'period_id' => $activePeriod->id,
            'report_date' => $reportDate,
            'done_tasks' => $data['done_tasks'],
            'next_tasks' => $data['next_tasks'],
            'appendix_link' => $data['appendix_link'] ?? null,
            'status' => 'submitted',
        ]);

        return redirect()->route('logbook.form')->with('status', 'Report logbook berhasil disubmit.');
    }
}
