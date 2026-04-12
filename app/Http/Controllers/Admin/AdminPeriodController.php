<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Institution;
use App\Models\Period;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class AdminPeriodController extends Controller
{
    private const DEFAULT_NEW_USER_PASSWORD = 'password123';

    public function index(): View
    {
        return view('admin.periods.index', [
            'periods' => Period::query()
                ->where('type', Period::TYPE_INTERNSHIP)
                ->with('institution:id,name,type')
                ->orderByDesc('start_date')
                ->paginate(20)
                ->withQueryString(),
            'institutions' => Institution::query()->orderBy('name')->get(['id', 'name', 'type']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'institution_id' => ['required', Rule::exists('institutions', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'holidays' => ['nullable', 'string'],
            'new_users' => ['nullable', 'string'],
        ]);

        $newUsers = $this->parseNewUsers($validated['new_users'] ?? null);
        $this->ensureNewUserEmailsAreAvailable($newUsers);

        DB::transaction(function () use ($validated, $newUsers): void {
            Period::create([
                'institution_id' => (int) $validated['institution_id'],
                'type' => Period::TYPE_INTERNSHIP,
                'name' => $validated['name'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'holidays' => $this->normalizeHolidays($validated['holidays'] ?? null),
            ]);

            $this->createUsersForInstitution((int) $validated['institution_id'], $newUsers);
        });

        $status = 'Periode berhasil ditambahkan.';
        if ($newUsers !== []) {
            $status .= ' '.count($newUsers).' user intern baru dibuat dengan password default: '.self::DEFAULT_NEW_USER_PASSWORD.'.';
        }

        return back()->with('status', $status);
    }

    public function update(Request $request, Period $period): RedirectResponse
    {
        if ($period->type === Period::TYPE_SPRINT) {
            return back()->withErrors(['period' => 'Period sprint dikelola otomatis dari aktivasi project.']);
        }

        $validated = $request->validate([
            'institution_id' => ['required', Rule::exists('institutions', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'holidays' => ['nullable', 'string'],
        ]);

        $period->update([
            'institution_id' => (int) $validated['institution_id'],
            'type' => Period::TYPE_INTERNSHIP,
            'name' => $validated['name'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'holidays' => $this->normalizeHolidays($validated['holidays'] ?? null),
        ]);

        return back()->with('status', 'Periode berhasil diubah.');
    }

    public function destroy(Period $period): RedirectResponse
    {
        if ($period->type === Period::TYPE_SPRINT) {
            return back()->withErrors(['period' => 'Period sprint dikelola otomatis dari aktivasi project.']);
        }

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

    /**
     * @return array<int, array{name: string, email: string}>
     */
    private function parseNewUsers(?string $raw): array
    {
        if (! $raw) {
            return [];
        }

        $rows = collect(preg_split('/\r\n|\r|\n/', $raw) ?: [])
            ->map(static fn (string $line): string => trim($line))
            ->filter()
            ->values();

        if ($rows->isEmpty()) {
            return [];
        }

        $users = [];
        $lineErrors = [];

        foreach ($rows as $index => $line) {
            $lineNumber = $index + 1;

            if (str_contains($line, '|')) {
                [$nameRaw, $emailRaw] = explode('|', $line, 2);
            } elseif (str_contains($line, ',')) {
                [$nameRaw, $emailRaw] = explode(',', $line, 2);
            } else {
                $lineErrors[] = "Baris {$lineNumber} harus format Nama|email@domain.com";
                continue;
            }

            $name = trim($nameRaw);
            $email = strtolower(trim($emailRaw));

            if ($name === '') {
                $lineErrors[] = "Baris {$lineNumber}: nama wajib diisi.";
            }

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $lineErrors[] = "Baris {$lineNumber}: email tidak valid.";
            }

            $users[] = [
                'name' => $name,
                'email' => $email,
            ];
        }

        $emailDuplicates = collect($users)
            ->pluck('email')
            ->filter()
            ->duplicates()
            ->unique()
            ->values()
            ->all();

        if ($emailDuplicates !== []) {
            $lineErrors[] = 'Email duplikat di input: '.implode(', ', $emailDuplicates);
        }

        if ($lineErrors !== []) {
            throw ValidationException::withMessages([
                'new_users' => $lineErrors,
            ]);
        }

        return $users;
    }

    /**
     * @param  array<int, array{name: string, email: string}>  $newUsers
     */
    private function ensureNewUserEmailsAreAvailable(array $newUsers): void
    {
        if ($newUsers === []) {
            return;
        }

        $emails = collect($newUsers)->pluck('email')->values()->all();

        $existingEmails = User::query()
            ->whereIn('email', $emails)
            ->pluck('email')
            ->map(static fn (string $email): string => strtolower($email))
            ->unique()
            ->values()
            ->all();

        if ($existingEmails !== []) {
            throw ValidationException::withMessages([
                'new_users' => ['Email sudah terdaftar: '.implode(', ', $existingEmails)],
            ]);
        }
    }

    /**
     * @param  array<int, array{name: string, email: string}>  $newUsers
     */
    private function createUsersForInstitution(int $institutionId, array $newUsers): void
    {
        if ($newUsers === []) {
            return;
        }

        Role::findOrCreate('Intern', 'web');

        foreach ($newUsers as $newUser) {
            $user = User::query()->create([
                'name' => $newUser['name'],
                'email' => $newUser['email'],
                'password' => Hash::make(self::DEFAULT_NEW_USER_PASSWORD),
                'institution_id' => $institutionId,
            ]);

            $user->assignRole('Intern');
        }
    }
}
