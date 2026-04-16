<?php

namespace App\Http\Controllers;

use App\Models\Period;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'institution_id' => ['required', 'exists:institutions,id'],
        ]);

        $hasInternshipPeriod = Period::query()
            ->where('institution_id', (int) $validated['institution_id'])
            ->where('type', Period::TYPE_INTERNSHIP)
            ->exists();

        if ( ! $hasInternshipPeriod) {
            return response()->json([
                'message' => 'Sebelum membuat user intern, buat dulu period magang (internship) untuk institusi ini.',
            ], 422);
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'institution_id' => (int) $validated['institution_id'],
        ]);

        Role::findOrCreate('Intern', 'web');
        $user->assignRole('Intern');

        return response()->json([
            'message' => 'User registered successfully',
            'data' => $user,
        ], 201);
    }
}
