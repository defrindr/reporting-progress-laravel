<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class AdminRoleController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', 'string', 'in:name,created_at,updated_at'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
        ]);

        $search = trim((string) ($validated['q'] ?? ''));
        $sort = (string) ($validated['sort'] ?? 'name');
        $direction = (string) ($validated['direction'] ?? 'asc');

        $rolesQuery = Role::query();

        if ($search !== '') {
            $rolesQuery->where('name', 'like', "%{$search}%");
        }

        return view('admin.roles.index', [
            'roles' => $rolesQuery
                ->orderBy($sort, $direction)
                ->paginate(20)
                ->withQueryString(),
            'filters' => [
                'q' => $search,
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:roles,name'],
        ]);

        Role::create([
            'name' => $validated['name'],
            'guard_name' => 'web',
        ]);

        return back()->with('status', 'Role berhasil ditambahkan.');
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:roles,name,'.$role->id],
        ]);

        $role->update(['name' => $validated['name']]);

        return back()->with('status', 'Role berhasil diubah.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        $role->delete();

        return back()->with('status', 'Role berhasil dihapus.');
    }
}
