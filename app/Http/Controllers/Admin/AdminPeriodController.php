<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GlobalHoliday;
use App\Models\Institution;
use App\Models\Period;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminPeriodController extends Controller
{
    private const DEFAULT_NEW_USER_PASSWORD = 'password123';

    private const NAGER_ENDPOINT = 'https://date.nager.at/api/v3/PublicHolidays/%d/%s';

    private const DENO_LIBUR_ENDPOINT = 'https://libur.deno.dev/api?year=%d';

    /**
     * @var array<string, string>
     */
    private const GLOBAL_HOLIDAY_COUNTRIES = [
        'ID' => 'Indonesia',
    ];

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'institution_id' => ['nullable', 'integer', Rule::exists('institutions', 'id')],
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'sort' => ['nullable', 'string', 'in:name,start_date,end_date,created_at'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
            'holiday_year' => ['nullable', 'integer', 'between:2000,2100'],
            'holiday_country' => ['nullable', Rule::in(array_keys(self::GLOBAL_HOLIDAY_COUNTRIES))],
        ]);

        $search = trim((string) ($validated['q'] ?? ''));
        $institutionId = isset($validated['institution_id']) ? (int) $validated['institution_id'] : null;
        $year = isset($validated['year']) ? (int) $validated['year'] : null;
        $sort = (string) ($validated['sort'] ?? 'start_date');
        $direction = (string) ($validated['direction'] ?? 'desc');
        $globalHolidayYear = isset($validated['holiday_year']) ? (int) $validated['holiday_year'] : (int) now()->year;
        $globalHolidayCountry = (string) ($validated['holiday_country'] ?? 'ID');

        $institutions = Institution::query()->orderBy('name')->get(['id', 'name', 'type']);

        $periodsQuery = Period::query()
            ->where('type', Period::TYPE_INTERNSHIP)
            ->with([
                'institution:id,name,type',
                'interns:id,name,email,institution_id',
            ]);

        if ($search !== '') {
            $periodsQuery->where(function ($query) use ($search): void {
                $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhereHas('institution', static function ($institutionQuery) use ($search): void {
                        $institutionQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($institutionId) {
            $periodsQuery->where('institution_id', $institutionId);
        }

        if ($year) {
            $periodsQuery->where(function ($query) use ($year): void {
                $query
                    ->whereYear('start_date', $year)
                    ->orWhereYear('end_date', $year);
            });
        }

        $globalHolidays = GlobalHoliday::query()
            ->where('year', $globalHolidayYear)
            ->where('country_code', $globalHolidayCountry)
            ->orderBy('holiday_date')
            ->get(['id', 'holiday_date', 'name']);

        $internOptionsByInstitution = User::query()
            ->role('Intern')
            ->whereIn('institution_id', $institutions->pluck('id'))
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'institution_id'])
            ->groupBy('institution_id')
            ->map(
                static fn (Collection $group): array => $group
                    ->map(static fn (User $user): array => [
                        'id' => (int) $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                    ])
                    ->values()
                    ->all()
            )
            ->all();

        return view('admin.periods.index', [
            'periods' => $periodsQuery
                ->orderBy($sort, $direction)
                ->orderByDesc('id')
                ->paginate(20)
                ->withQueryString(),
            'institutions' => $institutions,
            'filters' => [
                'q' => $search,
                'institution_id' => $institutionId,
                'year' => $year,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'internOptionsByInstitution' => $internOptionsByInstitution,
            'holidayCountries' => self::GLOBAL_HOLIDAY_COUNTRIES,
            'globalHolidayYear' => $globalHolidayYear,
            'globalHolidayCountry' => $globalHolidayCountry,
            'globalHolidays' => $globalHolidays,
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
            'intern_ids' => ['nullable', 'array'],
            'intern_ids.*' => ['integer', Rule::exists('users', 'id')],
            'new_users' => ['nullable', 'array'],
            'new_users.*.name' => ['nullable', 'string', 'max:255'],
            'new_users.*.email' => ['nullable', 'email', 'max:255'],
        ]);

        $institutionId = (int) $validated['institution_id'];

        $selectedInternIdsInput = collect($validated['intern_ids'] ?? [])
            ->map(static fn ($id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        $selectedInternIds = $this->resolveValidInternIdsForInstitution(
            institutionId: $institutionId,
            internIds: $selectedInternIdsInput,
        );

        if (count($selectedInternIdsInput) !== count($selectedInternIds)) {
            throw ValidationException::withMessages([
                'intern_ids' => ['List siswa magang hanya boleh berisi user role Intern dari institusi yang dipilih.'],
            ]);
        }

        $newUsers = $this->normalizeNewUsersInput($validated['new_users'] ?? null);
        $this->ensureNewUserEmailsAreAvailable($newUsers);
        $createdUsers = collect();
        $period = null;

        DB::transaction(function () use ($validated, $newUsers, $selectedInternIds, &$createdUsers, &$period, $institutionId): void {
            $period = Period::create([
                'institution_id' => $institutionId,
                'type' => Period::TYPE_INTERNSHIP,
                'name' => $validated['name'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'holidays' => $this->resolvePeriodHolidays(
                    raw: $validated['holidays'] ?? null,
                    startDate: $validated['start_date'],
                    endDate: $validated['end_date'],
                ),
            ]);

            $createdUsers = $this->createUsersForInstitution($institutionId, $newUsers);

            $participantIds = collect($selectedInternIds)
                ->merge(
                    $createdUsers
                        ->pluck('id')
                        ->map(static fn (int $id): int => (int) $id)
                        ->all()
                )
                ->unique()
                ->values()
                ->all();

            if ($period && $participantIds !== []) {
                $period->interns()->sync($participantIds);
            }
        });

        $status = 'Periode berhasil ditambahkan.';
        if ($newUsers !== []) {
            $status .= ' '.count($newUsers).' user intern baru dibuat dengan password default: '.self::DEFAULT_NEW_USER_PASSWORD.'.';
        }

        return back()
            ->with('status', $status)
            ->with('new_user_ids', $createdUsers->pluck('id')->map(static fn (int $id): int => (int) $id)->all());
    }

    public function downloadNewUsersCsv(Request $request): StreamedResponse|RedirectResponse
    {
        $ids = collect(explode(',', (string) $request->query('ids')))
            ->map(static fn (string $id): string => trim($id))
            ->filter(static fn (string $id): bool => ctype_digit($id))
            ->map(static fn (string $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return back()->withErrors(['new_users' => 'Tidak ada data user baru untuk diunduh.']);
        }

        $users = User::query()
            ->with('institution:id,name')
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->get(['id', 'name', 'email', 'institution_id', 'created_at']);

        if ($users->isEmpty()) {
            return back()->withErrors(['new_users' => 'User baru tidak ditemukan untuk diunduh.']);
        }

        $filename = 'period-new-users-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($users): void {
            $stream = fopen('php://output', 'w');

            if ($stream === false) {
                return;
            }

            fputcsv($stream, ['name', 'email', 'institution', 'default_password', 'created_at']);

            foreach ($users as $user) {
                fputcsv($stream, [
                    $user->name,
                    $user->email,
                    $user->institution?->name ?? '-',
                    self::DEFAULT_NEW_USER_PASSWORD,
                    optional($user->created_at)->toDateTimeString(),
                ]);
            }

            fclose($stream);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function syncGlobalHolidays(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'year' => ['required', 'integer', 'between:2000,2100'],
            'country_code' => ['required', Rule::in(array_keys(self::GLOBAL_HOLIDAY_COUNTRIES))],
        ]);

        $year = (int) $validated['year'];
        $countryCode = strtoupper((string) $validated['country_code']);

        [$nagerRows, $nagerOk] = $this->fetchNagerHolidays($year, $countryCode);
        [$denoRows, $denoOk] = $this->fetchLiburDenoHolidays($year, $countryCode);

        if (! $nagerOk && ! $denoOk) {
            return back()->withErrors([
                'global_holidays' => 'Gagal mengambil data hari libur dari dua sumber eksternal (Nager + libur.deno.dev). Coba lagi sebentar.',
            ]);
        }

        // Deduplicate by date and prefer libur.deno.dev labels for Indonesia when available.
        $rowsByDate = collect();
        foreach ($nagerRows as $row) {
            $rowsByDate->put($row['holiday_date'], $row);
        }
        foreach ($denoRows as $row) {
            $rowsByDate->put($row['holiday_date'], $row);
        }

        $payload = $rowsByDate
            ->sortKeys()
            ->values()
            ->all();

        DB::transaction(function () use ($countryCode, $year, $payload): void {
            GlobalHoliday::query()
                ->where('country_code', $countryCode)
                ->where('year', $year)
                ->delete();

            if ($payload !== []) {
                GlobalHoliday::query()->insert($payload);
            }
        });

        return redirect()
            ->route('admin.periods.index', [
                'holiday_year' => $year,
                'holiday_country' => $countryCode,
            ])
            ->with('status', 'Global hari libur berhasil disinkronkan: '.count($payload).' tanggal (Nager: '.count($nagerRows).', libur.deno.dev: '.count($denoRows).').');
    }

    /**
     * @return array{0: array<int, array{holiday_date: string, name: string, country_code: string, year: int, source: string, created_at: Carbon, updated_at: Carbon}>, 1: bool}
     */
    private function fetchNagerHolidays(int $year, string $countryCode): array
    {
        try {
            $response = Http::timeout(20)
                ->acceptJson()
                ->get(sprintf(self::NAGER_ENDPOINT, $year, $countryCode));

            if (! $response->successful()) {
                return [[], false];
            }

            $rows = collect($response->json())
                ->filter(static fn ($item): bool => is_array($item) && filled($item['date'] ?? null))
                ->map(static function (array $item) use ($countryCode, $year): array {
                    $holidayDate = Carbon::parse((string) $item['date'])->toDateString();

                    return [
                        'holiday_date' => $holidayDate,
                        'name' => trim((string) ($item['localName'] ?? $item['name'] ?? 'Hari Libur')),
                        'country_code' => $countryCode,
                        'year' => $year,
                        'source' => 'nager',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                })
                ->keyBy('holiday_date')
                ->sortKeys()
                ->values()
                ->all();

            return [$rows, true];
        } catch (\Throwable $exception) {
            Log::warning('Nager holiday sync failed.', [
                'year' => $year,
                'country_code' => $countryCode,
                'error' => $exception->getMessage(),
            ]);

            return [[], false];
        }
    }

    /**
     * @return array{0: array<int, array{holiday_date: string, name: string, country_code: string, year: int, source: string, created_at: Carbon, updated_at: Carbon}>, 1: bool}
     */
    private function fetchLiburDenoHolidays(int $year, string $countryCode): array
    {
        if ($countryCode !== 'ID') {
            return [[], true];
        }

        try {
            $response = Http::timeout(20)
                ->acceptJson()
                ->get(sprintf(self::DENO_LIBUR_ENDPOINT, $year));

            if (! $response->successful()) {
                return [[], false];
            }

            $rows = collect($response->json())
                ->filter(static fn ($item): bool => is_array($item) && filled($item['date'] ?? null))
                ->map(static function (array $item) use ($countryCode, $year): array {
                    $holidayDate = Carbon::parse((string) $item['date'])->toDateString();

                    return [
                        'holiday_date' => $holidayDate,
                        'name' => trim((string) ($item['name'] ?? 'Hari Libur')),
                        'country_code' => $countryCode,
                        'year' => $year,
                        'source' => 'libur.deno.dev',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                })
                ->keyBy('holiday_date')
                ->sortKeys()
                ->values()
                ->all();

            return [$rows, true];
        } catch (\Throwable $exception) {
            Log::warning('libur.deno.dev holiday sync failed.', [
                'year' => $year,
                'country_code' => $countryCode,
                'error' => $exception->getMessage(),
            ]);

            return [[], false];
        }
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
            'holidays' => $this->resolvePeriodHolidays(
                raw: $validated['holidays'] ?? null,
                startDate: $validated['start_date'],
                endDate: $validated['end_date'],
            ),
        ]);

        $validParticipantIds = $this->resolveValidInternIdsForInstitution(
            institutionId: (int) $validated['institution_id'],
            internIds: $period->interns()->pluck('users.id')->map(static fn (int $id): int => (int) $id)->all(),
        );

        $period->interns()->sync($validParticipantIds);

        return back()->with('status', 'Periode berhasil diubah.');
    }

    public function updateParticipants(Request $request, Period $period): RedirectResponse
    {
        if ($period->type === Period::TYPE_SPRINT) {
            return back()->withErrors(['period' => 'Daftar siswa magang hanya tersedia untuk period internship.']);
        }

        $validated = $request->validate([
            'intern_ids' => ['nullable', 'array'],
            'intern_ids.*' => ['integer', Rule::exists('users', 'id')],
        ]);

        $internIdsInput = collect($validated['intern_ids'] ?? [])
            ->map(static fn ($id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        $validInternIds = $this->resolveValidInternIdsForInstitution(
            institutionId: (int) $period->institution_id,
            internIds: $internIdsInput,
        );

        if (count($internIdsInput) !== count($validInternIds)) {
            throw ValidationException::withMessages([
                'intern_ids' => ['List siswa magang hanya boleh berisi user role Intern dari institusi period ini.'],
            ]);
        }

        $period->interns()->sync($validInternIds);

        return back()->with('status', 'List siswa magang period berhasil diperbarui.');
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
     * @return array<int, string>
     */
    private function resolvePeriodHolidays(?string $raw, string $startDate, string $endDate): array
    {
        $manualHolidays = $this->normalizeHolidays($raw);
        $globalHolidays = $this->globalHolidayDatesForRange($startDate, $endDate);

        return collect([...$globalHolidays, ...$manualHolidays])
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function globalHolidayDatesForRange(string $startDate, string $endDate): array
    {
        return GlobalHoliday::query()
            ->whereBetween('holiday_date', [
                Carbon::parse($startDate)->toDateString(),
                Carbon::parse($endDate)->toDateString(),
            ])
            ->orderBy('holiday_date')
            ->pluck('holiday_date')
            ->map(static fn ($date): string => Carbon::parse((string) $date)->toDateString())
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int>  $internIds
     * @return array<int, int>
     */
    private function resolveValidInternIdsForInstitution(int $institutionId, array $internIds): array
    {
        if ($internIds === []) {
            return [];
        }

        return User::query()
            ->role('Intern')
            ->where('institution_id', $institutionId)
            ->whereIn('id', $internIds)
            ->pluck('id')
            ->map(static fn (int $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{name: string, email: string}>
     */
    private function normalizeNewUsersInput(?array $rows): array
    {
        if (! $rows) {
            return [];
        }

        $normalizedRows = collect($rows)
            ->map(static fn (array $row): array => [
                'name' => trim((string) ($row['name'] ?? '')),
                'email' => strtolower(trim((string) ($row['email'] ?? ''))),
            ])
            ->filter(static fn (array $row): bool => $row['name'] !== '' || $row['email'] !== '')
            ->values();

        if ($normalizedRows->isEmpty()) {
            return [];
        }

        $users = $normalizedRows->all();
        $errors = [];

        foreach ($users as $index => $user) {
            $lineNumber = $index + 1;

            if ($user['name'] === '' || $user['email'] === '') {
                $errors[] = "Baris {$lineNumber}: nama dan email wajib diisi.";

                continue;
            }

            if (! filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Baris {$lineNumber}: email tidak valid.";
            }
        }

        $emailDuplicates = collect($users)
            ->pluck('email')
            ->filter()
            ->duplicates()
            ->unique()
            ->values()
            ->all();

        if ($emailDuplicates !== []) {
            $errors[] = 'Email duplikat di input: '.implode(', ', $emailDuplicates);
        }

        if ($errors !== []) {
            throw ValidationException::withMessages([
                'new_users' => $errors,
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
    private function createUsersForInstitution(int $institutionId, array $newUsers): Collection
    {
        if ($newUsers === []) {
            return collect();
        }

        Role::findOrCreate('Intern', 'web');
        $createdUsers = collect();

        foreach ($newUsers as $newUser) {
            $user = User::query()->create([
                'name' => $newUser['name'],
                'email' => $newUser['email'],
                'password' => Hash::make(self::DEFAULT_NEW_USER_PASSWORD),
                'institution_id' => $institutionId,
            ]);

            $user->assignRole('Intern');
            $createdUsers->push($user);
        }

        return $createdUsers;
    }
}
