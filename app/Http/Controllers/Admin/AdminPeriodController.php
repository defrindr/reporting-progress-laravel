<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Period;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminPeriodController extends Controller
{
    public function index(): View
    {
        return view('admin.periods.index', [
            'periods' => Period::query()->orderByDesc('start_date')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'holidays' => ['nullable', 'string'],
        ]);

        Period::create([
            'name' => $validated['name'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'holidays' => $this->normalizeHolidays($validated['holidays'] ?? null),
        ]);

        return back()->with('status', 'Periode berhasil ditambahkan.');
    }

    public function update(Request $request, Period $period): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'holidays' => ['nullable', 'string'],
        ]);

        $period->update([
            'name' => $validated['name'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'holidays' => $this->normalizeHolidays($validated['holidays'] ?? null),
        ]);

        return back()->with('status', 'Periode berhasil diubah.');
    }

    public function destroy(Period $period): RedirectResponse
    {
        $period->delete();

        return back()->with('status', 'Periode berhasil dihapus.');
    }

    /**
     * @return array<int, string>
     */
    private function normalizeHolidays(?string $raw): array
    {
        if (! $raw) {
            return [];
        }

        return collect(explode(',', $raw))
            ->map(fn (string $date): string => trim($date))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
