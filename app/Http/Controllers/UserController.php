<?php

namespace App\Http\Controllers;

use App\Models\Period;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => User::query()->with('roles')->orderBy('id')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'institution_id' => ['nullable', 'exists:institutions,id'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
        ]);

        $targetRoles = $validated['roles'] ?? ['Intern'];
        $institutionId = isset($validated['institution_id']) ? (int) $validated['institution_id'] : null;

        if ($error = $this->internOnboardingError($targetRoles, $institutionId)) {
            return response()->json(['message' => $error], 422);
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'institution_id' => $validated['institution_id'] ?? null,
        ]);

        if (! array_key_exists('roles', $validated)) {
            Role::findOrCreate('Intern', 'web');
        }

        $user->syncRoles($targetRoles);

        return response()->json(['data' => $user->load('roles')], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'password' => ['sometimes', 'string', 'min:8'],
            'institution_id' => ['nullable', 'exists:institutions,id'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
        ]);

        $targetRoles = $validated['roles'] ?? $user->roles()->pluck('name')->values()->all();
        $institutionId = array_key_exists('institution_id', $validated)
            ? (isset($validated['institution_id']) ? (int) $validated['institution_id'] : null)
            : (isset($user->institution_id) ? (int) $user->institution_id : null);

        if ($error = $this->internOnboardingError($targetRoles, $institutionId)) {
            return response()->json(['message' => $error], 422);
        }

        if (array_key_exists('password', $validated)) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $user->update($validated);

        if (array_key_exists('roles', $validated)) {
            $user->syncRoles($validated['roles']);
        }

        return response()->json(['data' => $user->load('roles')]);
    }

    public function destroy(User $user): JsonResponse
    {
        $user->delete();

        return response()->json(status: 204);
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
