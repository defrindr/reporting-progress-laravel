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
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', Rule::in(['university', 'vocational'])],
            'sort' => ['nullable', 'string', 'in:name,type,created_at'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
        ]);

        $search = trim((string) ($validated['q'] ?? ''));
        $type = (string) ($validated['type'] ?? '');
        $sort = (string) ($validated['sort'] ?? 'name');
        $direction = (string) ($validated['direction'] ?? 'asc');

        $institutionsQuery = Institution::query();

        if ($search !== '') {
            $institutionsQuery->where('name', 'like', "%{$search}%");
        }

        if ($type !== '') {
            $institutionsQuery->where('type', $type);
        }

        return view('admin.institutions.index', [
            'institutions' => $institutionsQuery
                ->orderBy($sort, $direction)
                ->paginate(20)
                ->withQueryString(),
            'filters' => [
                'q' => $search,
                'type' => $type,
                'sort' => $sort,
                'direction' => $direction,
            ],
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
