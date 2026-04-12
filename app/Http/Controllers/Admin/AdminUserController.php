<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Institution;
use App\Models\Period;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class AdminUserController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', 'string', Rule::exists('roles', 'name')],
            'institution_id' => ['nullable', 'integer', Rule::exists('institutions', 'id')],
            'sort' => ['nullable', 'string', 'in:name,email,created_at'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
        ]);

        $search = trim((string) ($validated['q'] ?? ''));
        $role = (string) ($validated['role'] ?? '');
        $institutionId = isset($validated['institution_id']) ? (int) $validated['institution_id'] : null;
        $sort = (string) ($validated['sort'] ?? 'created_at');
        $direction = (string) ($validated['direction'] ?? 'desc');

        $usersQuery = User::query()->with(['roles', 'institution:id,name']);

        if ($search !== '') {
            $usersQuery->where(function ($query) use ($search): void {
                $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($role !== '') {
            $usersQuery->whereHas('roles', static function ($query) use ($role): void {
                $query->where('name', $role);
            });
        }

        if ($institutionId) {
            $usersQuery->where('institution_id', $institutionId);
        }

        return view('admin.users.index', [
            'users' => $usersQuery
                ->orderBy($sort, $direction)
                ->paginate(20)
                ->withQueryString(),
            'roles' => Role::query()->orderBy('name')->get(),
            'institutions' => Institution::query()->orderBy('name')->get(),
            'filters' => [
                'q' => $search,
                'role' => $role,
                'institution_id' => $institutionId,
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'institution_id' => ['nullable', 'exists:institutions,id'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', 'exists:roles,name'],
        ]);

        if ($error = $this->internOnboardingError($validated['roles'], isset($validated['institution_id']) ? (int) $validated['institution_id'] : null)) {
            return back()->withErrors(['roles' => $error])->withInput();
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'institution_id' => $validated['institution_id'] ?? null,
        ]);

        $user->syncRoles($validated['roles']);

        return back()->with('status', 'User berhasil ditambahkan.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'password' => ['nullable', 'string', 'min:8'],
            'institution_id' => ['nullable', 'exists:institutions,id'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', 'exists:roles,name'],
        ]);

        if ($error = $this->internOnboardingError($validated['roles'], isset($validated['institution_id']) ? (int) $validated['institution_id'] : null)) {
            return back()->withErrors(['roles' => $error])->withInput();
        }

        $payload = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'institution_id' => $validated['institution_id'] ?? null,
        ];

        if (! empty($validated['password'])) {
            $payload['password'] = Hash::make($validated['password']);
        }

        $user->update($payload);
        $user->syncRoles($validated['roles']);

        return back()->with('status', 'User berhasil diubah.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if (Auth::id() === $user->id) {
            return back()->withErrors(['user' => 'Tidak bisa menghapus akun sendiri.']);
        }

        DB::transaction(function () use ($user): void {
            Project::query()
                ->where('assignee_id', $user->id)
                ->update(['assignee_id' => null]);

            $user->assignedProjectSpecs()->detach();
            $user->delete();
        });

        return back()->with('status', 'User berhasil dihapus.');
    }

    /**
     * @param  array<int, string>  $roles
     */
    private function internOnboardingError(array $roles, ?int $institutionId): ?string
    {
        if (! in_array('Intern', $roles, true)) {
            return null;
        }

        if (! $institutionId) {
            return 'User intern wajib memilih institusi terlebih dahulu.';
        }

        $hasInternshipPeriod = Period::query()
            ->where('institution_id', $institutionId)
            ->where('type', Period::TYPE_INTERNSHIP)
            ->exists();

        if (! $hasInternshipPeriod) {
            return 'Sebelum membuat user intern, buat dulu period magang (internship) untuk institusi ini.';
        }

        return null;
    }
}
