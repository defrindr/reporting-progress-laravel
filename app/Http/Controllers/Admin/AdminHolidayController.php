<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GlobalHoliday;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminHolidayController extends Controller
{
    private const NAGER_ENDPOINT = 'https://date.nager.at/api/v3/PublicHolidays/%d/%s';

    private const DENO_LIBUR_ENDPOINT = 'https://libur.deno.dev/api?year=%d';

    private const GLOBAL_HOLIDAY_COUNTRIES = [
        'ID' => 'Indonesia',
    ];

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'country_code' => ['nullable', Rule::in(array_keys(self::GLOBAL_HOLIDAY_COUNTRIES))],
            'type' => ['nullable', 'string', 'in:all,synced,company'],
        ]);

        $year = isset($validated['year']) ? (int) $validated['year'] : (int) now()->year;
        $countryCode = (string) ($validated['country_code'] ?? 'ID');
        $type = (string) ($validated['type'] ?? 'all');

        $holidaysQuery = GlobalHoliday::query()
            ->where('year', $year)
            ->where('country_code', $countryCode)
            ->with('creator:id,name');

        if ($type === 'synced') {
            $holidaysQuery->where('is_company_holiday', false);
        } elseif ($type === 'company') {
            $holidaysQuery->where('is_company_holiday', true);
        }

        $holidays = $holidaysQuery->orderBy('holiday_date')->paginate(20);

        return view('admin.holidays.index', [
            'holidays' => $holidays,
            'year' => $year,
            'countryCode' => $countryCode,
            'type' => $type,
            'holidayCountries' => self::GLOBAL_HOLIDAY_COUNTRIES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'holiday_date' => ['required', 'date'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'country_code' => ['required', Rule::in(array_keys(self::GLOBAL_HOLIDAY_COUNTRIES))],
        ]);

        $year = Carbon::parse($validated['holiday_date'])->year;

        GlobalHoliday::create([
            'holiday_date' => $validated['holiday_date'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'country_code' => $validated['country_code'],
            'year' => $year,
            'source' => 'manual',
            'is_company_holiday' => true,
            'created_by_admin_id' => $request->user()->id,
        ]);

        return back()->with('status', 'Hari libur perusahaan berhasil ditambahkan.');
    }

    public function update(Request $request, GlobalHoliday $holiday): RedirectResponse
    {
        $validated = $request->validate([
            'holiday_date' => ['required', 'date'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $year = Carbon::parse($validated['holiday_date'])->year;

        $holiday->update([
            'holiday_date' => $validated['holiday_date'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'year' => $year,
        ]);

        return back()->with('status', 'Hari libur berhasil diperbarui.');
    }

    public function destroy(GlobalHoliday $holiday): RedirectResponse
    {
        $holiday->delete();

        return back()->with('status', 'Hari libur berhasil dihapus.');
    }

    public function sync(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'year' => ['required', 'integer', 'between:2000,2100'],
            'country_code' => ['required', Rule::in(array_keys(self::GLOBAL_HOLIDAY_COUNTRIES))],
        ]);

        $year = (int) $validated['year'];
        $countryCode = strtoupper((string) $validated['country_code']);

        [$nagerRows, $nagerOk] = $this->fetchNagerHolidays($year, $countryCode);
        [$denoRows, $denoOk] = $this->fetchLiburDenoHolidays($year, $countryCode);

        if ( ! $nagerOk && ! $denoOk) {
            return back()->withErrors([
                'global_holidays' => 'Gagal mengambil data hari libur dari dua sumber eksternal (Nager + libur.deno.dev). Coba lagi sebentar.',
            ]);
        }

        $rowsByDate = collect();
        foreach ($nagerRows as $row) {
            $rowsByDate->put($row['holiday_date'], $row);
        }
        foreach ($denoRows as $row) {
            $rowsByDate->put($row['holiday_date'], $row);
        }

        $payload = $rowsByDate->sortKeys()->values()->all();

        GlobalHoliday::query()
            ->where('country_code', $countryCode)
            ->where('year', $year)
            ->where('is_company_holiday', false)
            ->delete();

        if ($payload !== []) {
            GlobalHoliday::query()->insert($payload);
        }

        return redirect()
            ->route('admin.holidays.index', [
                'year' => $year,
                'country_code' => $countryCode,
            ])
            ->with('status', 'Global hari libur berhasil disinkronkan: '.count($payload).' tanggal.');
    }

    private function fetchNagerHolidays(int $year, string $countryCode): array
    {
        try {
            $response = Http::timeout(20)
                ->acceptJson()
                ->get(sprintf(self::NAGER_ENDPOINT, $year, $countryCode));

            if ( ! $response->successful()) {
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
                        'is_company_holiday' => false,
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

    private function fetchLiburDenoHolidays(int $year, string $countryCode): array
    {
        if ($countryCode !== 'ID') {
            return [[], false];
        }

        try {
            $response = Http::timeout(20)
                ->acceptJson()
                ->get(sprintf(self::DENO_LIBUR_ENDPOINT, $year));

            if ( ! $response->successful()) {
                return [[], false];
            }

            $rows = collect($response->json())
                ->filter(static fn ($item): bool => is_array($item) && filled($item['date'] ?? null))
                ->map(static function (array $item) use ($year): array {
                    $holidayDate = Carbon::parse((string) $item['date'])->toDateString();

                    return [
                        'holiday_date' => $holidayDate,
                        'name' => trim((string) ($item['holiday'] ?? 'Hari Libur')),
                        'country_code' => 'ID',
                        'year' => $year,
                        'source' => 'libur.deno.dev',
                        'is_company_holiday' => false,
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
            Log::warning('Libur.deno holiday sync failed.', [
                'year' => $year,
                'error' => $exception->getMessage(),
            ]);

            return [[], false];
        }
    }
}
