<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Institution;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminInstitutionController extends Controller
{
    public function index(): View
    {
        return view('admin.institutions.index', [
            'institutions' => Institution::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['university', 'vocational'])],
        ]);

        Institution::create($validated);

        return back()->with('status', 'Institusi berhasil ditambahkan.');
    }

    public function update(Request $request, Institution $institution): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['university', 'vocational'])],
        ]);

        $institution->update($validated);

        return back()->with('status', 'Institusi berhasil diubah.');
    }

    public function destroy(Institution $institution): RedirectResponse
    {
        $institution->delete();

        return back()->with('status', 'Institusi berhasil dihapus.');
    }
}
