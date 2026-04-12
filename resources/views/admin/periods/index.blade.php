@extends('layouts.app')

@php
    $newUserIds = collect(session('new_user_ids', []))
        ->map(static fn ($id): int => (int) $id)
        ->filter(static fn (int $id): bool => $id > 0)
        ->unique()
        ->values();

    $oldNewUsers = old('new_users');
    if (! is_array($oldNewUsers) || $oldNewUsers === []) {
        $oldNewUsers = [['name' => '', 'email' => '']];
    }

    $internOptionsByInstitution = collect($internOptionsByInstitution ?? [])
        ->mapWithKeys(function ($interns, $institutionId): array {
            $rows = collect($interns)
                ->map(static fn ($intern): array => [
                    'id' => (int) ($intern['id'] ?? 0),
                    'name' => (string) ($intern['name'] ?? ''),
                    'email' => (string) ($intern['email'] ?? ''),
                ])
                ->filter(static fn (array $intern): bool => $intern['id'] > 0)
                ->values()
                ->all();

            return [(int) $institutionId => $rows];
        })
        ->all();

    $selectedHolidayCountry = (string) ($globalHolidayCountry ?? 'ID');
@endphp

@section('content')
    <section class="space-y-6">
        <header class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">CRUD Periods</h1>
                <p class="text-sm text-slate-500">Kelola period magang per institusi. Period sprint dibuat otomatis dari aktivasi sprint di halaman project detail.</p>
            </div>

            <button type="button" onclick="document.getElementById('create-period-modal').showModal()" class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">Tambah Period</button>
        </header>

        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold">Global Hari Libur</h2>
                    <p class="mt-1 text-xs text-slate-500">Sinkronkan tanggal merah dari sumber eksternal sekali per tahun, lalu semua period otomatis mengambil tanggal dalam rentang period.</p>
                </div>

                <form method="POST" action="{{ route('admin.periods.global-holidays.sync') }}" class="flex flex-wrap items-center gap-2">
                    @csrf
                    <input type="number" name="year" min="2000" max="2100" value="{{ $globalHolidayYear }}" class="w-28 rounded-xl border border-slate-300 px-3 py-2 text-sm">
                    <select name="country_code" class="rounded-xl border border-slate-300 px-3 py-2 text-sm">
                        @foreach ($holidayCountries as $code => $name)
                            <option value="{{ $code }}" @selected($selectedHolidayCountry === $code)>{{ $name }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">Sync Tanggal Merah</button>
                </form>
            </div>

            <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-3">
                <div class="mb-2 flex items-center justify-between">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Data Global {{ $globalHolidayYear }} ({{ $holidayCountries[$selectedHolidayCountry] ?? $selectedHolidayCountry }})</p>
                    <span class="rounded-full bg-white px-2 py-1 text-xs font-semibold text-slate-600">{{ $globalHolidays->count() }} tanggal</span>
                </div>

                @if ($globalHolidays->isEmpty())
                    <p class="text-sm text-slate-500">Belum ada data global hari libur untuk tahun ini. Klik tombol sync agar terisi otomatis.</p>
                @else
                    <div class="max-h-36 overflow-auto">
                        <ul class="space-y-1 text-sm text-slate-700">
                            @foreach ($globalHolidays as $holiday)
                                <li class="rounded-md bg-white px-2.5 py-1.5">
                                    <span class="font-medium">{{ optional($holiday->holiday_date)->toDateString() }}</span>
                                    <span class="text-slate-500">- {{ $holiday->name }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </article>

        @if ($newUserIds->isNotEmpty())
            <article class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900">
                <p>User baru berhasil dibuat. Kamu bisa download daftar akun untuk dibagikan.</p>
                <a href="{{ route('admin.periods.new-users-csv', ['ids' => $newUserIds->implode(',')]) }}"
                    class="rounded-lg bg-blue-700 px-3 py-2 text-xs font-semibold text-white hover:bg-blue-600">
                    Download CSV User Baru
                </a>
            </article>
        @endif

        <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <form method="GET" action="{{ route('admin.periods.index') }}" class="grid gap-3 xl:grid-cols-[1fr_220px_120px_170px_120px_auto_auto]">
                <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Cari nama period atau institusi..." class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">

                <select name="institution_id" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                    <option value="">Semua Institusi</option>
                    @foreach ($institutions as $institution)
                        <option value="{{ $institution->id }}" @selected((int) ($filters['institution_id'] ?? 0) === (int) $institution->id)>
                            {{ $institution->name }} ({{ $institution->type }})
                        </option>
                    @endforeach
                </select>

                <input type="number" name="year" min="2000" max="2100" value="{{ $filters['year'] ?? '' }}" placeholder="Tahun" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">

                <select name="sort" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                    <option value="start_date" @selected(($filters['sort'] ?? '') === 'start_date')>Sort: Start Date</option>
                    <option value="end_date" @selected(($filters['sort'] ?? '') === 'end_date')>Sort: End Date</option>
                    <option value="name" @selected(($filters['sort'] ?? '') === 'name')>Sort: Name</option>
                    <option value="created_at" @selected(($filters['sort'] ?? '') === 'created_at')>Sort: Created</option>
                </select>

                <select name="direction" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                    <option value="desc" @selected(($filters['direction'] ?? '') === 'desc')>DESC</option>
                    <option value="asc" @selected(($filters['direction'] ?? '') === 'asc')>ASC</option>
                </select>

                <button type="submit" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold hover:bg-slate-50">Terapkan</button>
                <a href="{{ route('admin.periods.index', ['holiday_year' => $globalHolidayYear, 'holiday_country' => $selectedHolidayCountry]) }}" class="rounded-xl border border-slate-300 px-4 py-2.5 text-center text-sm hover:bg-slate-50">Reset</a>
            </form>
        </article>

        <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-slate-600">
                    <tr>
                        <th class="px-4 py-3">No</th>
                        <th class="px-4 py-3">Data Period</th>
                        <th class="px-4 py-3">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($periods as $period)
                        <tr>
                            <td class="px-4 py-3 align-top">{{ ($periods->firstItem() ?? 0) + $loop->index }}</td>
                            <td class="px-4 py-3">
                                <p class="font-semibold">{{ $period->name }}</p>
                                <p class="text-xs text-slate-500">{{ optional($period->institution)->name ?? '-' }}</p>
                                <p class="text-xs text-slate-500">{{ optional($period->start_date)->toDateString() }} - {{ optional($period->end_date)->toDateString() }}</p>
                                <p class="text-xs text-slate-500">Siswa magang terdaftar: {{ $period->interns->count() }} orang</p>
                                <p class="mt-1 text-xs text-slate-500">Holidays: {{ implode(',', $period->holidays ?? []) ?: '-' }}</p>
                            </td>
                            <td class="px-4 py-3 align-top">
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        class="rounded-lg border border-blue-300 px-3 py-2 text-xs font-semibold text-blue-700 hover:bg-blue-50"
                                        data-participant-action="{{ route('admin.periods.participants.update', $period) }}"
                                        data-period-name="{{ $period->name }}"
                                        data-institution-id="{{ (int) $period->institution_id }}"
                                        data-participant-ids="{{ $period->interns->pluck('id')->implode(',') }}"
                                        onclick="openManageParticipantsModal(this)">
                                        Kelola Siswa
                                    </button>

                                    <button
                                        type="button"
                                        class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold hover:bg-slate-50"
                                        data-edit-action="{{ route('admin.periods.update', $period) }}"
                                        data-institution-id="{{ $period->institution_id ?? '' }}"
                                        data-name="{{ $period->name }}"
                                        data-start="{{ optional($period->start_date)->toDateString() }}"
                                        data-end="{{ optional($period->end_date)->toDateString() }}"
                                        data-holidays="{{ implode(',', $period->holidays ?? []) }}"
                                        onclick="openEditPeriodModal(this)">
                                        Edit
                                    </button>

                                    <form method="POST" action="{{ route('admin.periods.destroy', $period) }}" onsubmit="return confirm('Hapus period ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="rounded-lg bg-rose-600 px-3 py-2 text-xs font-semibold text-white hover:bg-rose-500">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <article class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-4 py-3">
                <h2 class="text-sm font-semibold text-slate-800">List Siswa Magang per Period</h2>
                <p class="mt-1 text-xs text-slate-500">Akses menu intern kini mengikuti daftar siswa yang terdaftar di period aktif, bukan hanya institusi.</p>
            </div>

            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-slate-600">
                    <tr>
                        <th class="px-4 py-3">No</th>
                        <th class="px-4 py-3">Period</th>
                        <th class="px-4 py-3">Institusi</th>
                        <th class="px-4 py-3">Siswa Magang</th>
                        <th class="px-4 py-3">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($periods as $period)
                        <tr>
                            <td class="px-4 py-3 align-top">{{ ($periods->firstItem() ?? 0) + $loop->index }}</td>
                            <td class="px-4 py-3 align-top">
                                <p class="font-semibold">{{ $period->name }}</p>
                                <p class="text-xs text-slate-500">{{ optional($period->start_date)->toDateString() }} - {{ optional($period->end_date)->toDateString() }}</p>
                            </td>
                            <td class="px-4 py-3 align-top">{{ optional($period->institution)->name ?? '-' }}</td>
                            <td class="px-4 py-3 align-top">
                                @if ($period->interns->isEmpty())
                                    <span class="rounded-md bg-amber-100 px-2 py-1 text-xs font-medium text-amber-800">Belum ada siswa</span>
                                @else
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach ($period->interns->sortBy('name') as $intern)
                                            <span class="rounded-md bg-slate-100 px-2 py-1 text-xs text-slate-700">{{ $intern->name }}</span>
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                            <td class="px-4 py-3 align-top">
                                <button
                                    type="button"
                                    class="rounded-lg border border-blue-300 px-3 py-2 text-xs font-semibold text-blue-700 hover:bg-blue-50"
                                    data-participant-action="{{ route('admin.periods.participants.update', $period) }}"
                                    data-period-name="{{ $period->name }}"
                                    data-institution-id="{{ (int) $period->institution_id }}"
                                    data-participant-ids="{{ $period->interns->pluck('id')->implode(',') }}"
                                    onclick="openManageParticipantsModal(this)">
                                    Kelola Siswa
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-sm text-slate-500">Belum ada data period.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </article>

        <div>
            {{ $periods->links() }}
        </div>
    </section>

    <dialog id="create-period-modal" class="w-full max-w-2xl rounded-2xl border border-slate-200 p-0 shadow-2xl backdrop:bg-slate-900/40">
        <form method="POST" action="{{ route('admin.periods.store') }}" class="space-y-4 p-5">
            @csrf

            <header class="flex items-center justify-between">
                <h2 class="text-lg font-semibold">Tambah Period</h2>
                <button type="button" onclick="document.getElementById('create-period-modal').close()" class="rounded-lg border border-slate-300 px-2 py-1 text-xs">Tutup</button>
            </header>

            <div class="grid gap-3 lg:grid-cols-2">
                <select id="create-period-institution" name="institution_id" required class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                    <option value="">Pilih Institusi</option>
                    @foreach ($institutions as $institution)
                        <option value="{{ $institution->id }}">{{ $institution->name }} ({{ $institution->type }})</option>
                    @endforeach
                </select>

                <select id="create-period-interns" name="intern_ids[]" multiple class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm" size="4">
                    <option value="">Pilih institusi dulu untuk menampilkan list siswa</option>
                </select>
                <p class="text-xs text-slate-500 lg:col-span-2">Opsional: pilih siswa magang yang langsung didaftarkan di period ini. User baru yang dibuat di bawah otomatis ikut didaftarkan.</p>

                <input name="name" type="text" required placeholder="Nama periode" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                <input name="start_date" type="date" required class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                <input name="end_date" type="date" required class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                <input name="holidays" type="text" placeholder="Tambahan khusus period: 2026-01-03,2026-01-04" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm lg:col-span-2">
                <p class="text-xs text-slate-500 lg:col-span-2">Global holiday otomatis diambil sesuai rentang period. Field ini hanya untuk tambahan libur khusus.</p>

                <div class="space-y-2 lg:col-span-2">
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">List User Baru (Opsional)</label>
                    <div id="new-user-rows" class="space-y-2">
                        @foreach ($oldNewUsers as $index => $newUser)
                            <div class="new-user-row grid gap-2 rounded-xl border border-slate-200 bg-slate-50 p-3 md:grid-cols-[1fr_1fr_auto]" data-index="{{ $index }}">
                                <input type="text" name="new_users[{{ $index }}][name]" value="{{ $newUser['name'] ?? '' }}"
                                    placeholder="Nama Intern"
                                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                <input type="email" name="new_users[{{ $index }}][email]" value="{{ $newUser['email'] ?? '' }}"
                                    placeholder="intern@institusi.ac.id"
                                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                <button type="button" class="rounded-lg border border-rose-300 px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-50"
                                    onclick="removeNewUserRow(this)">Hapus</button>
                            </div>
                        @endforeach
                    </div>

                    <button type="button" onclick="addNewUserRow()" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold hover:bg-slate-50">+ Tambah User</button>
                    <p class="text-xs text-slate-500">User akan dibuat sebagai Intern dengan password default <span class="font-semibold">password123</span>.</p>
                </div>
            </div>

            <button type="submit" class="w-full rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">Simpan</button>
        </form>
    </dialog>

    <dialog id="edit-period-modal" class="w-full max-w-2xl rounded-2xl border border-slate-200 p-0 shadow-2xl backdrop:bg-slate-900/40">
        <form id="edit-period-form" method="POST" class="space-y-4 p-5">
            @csrf
            @method('PUT')

            <header class="flex items-center justify-between">
                <h2 class="text-lg font-semibold">Edit Period</h2>
                <button type="button" onclick="document.getElementById('edit-period-modal').close()" class="rounded-lg border border-slate-300 px-2 py-1 text-xs">Tutup</button>
            </header>

            <div class="grid gap-3 lg:grid-cols-2">
                <select id="edit-period-institution" name="institution_id" required class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                    @foreach ($institutions as $institution)
                        <option value="{{ $institution->id }}">{{ $institution->name }} ({{ $institution->type }})</option>
                    @endforeach
                </select>

                <input id="edit-period-name" name="name" type="text" required class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                <input id="edit-period-start" name="start_date" type="date" required class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                <input id="edit-period-end" name="end_date" type="date" required class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                <input id="edit-period-holidays" name="holidays" type="text" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm lg:col-span-2">
                <p class="text-xs text-slate-500 lg:col-span-2">Kosongkan jika tidak ada tambahan libur khusus (global holiday tetap otomatis dipakai).</p>
            </div>

            <button type="submit" class="w-full rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">Update</button>
        </form>
    </dialog>

    <dialog id="manage-participants-modal" class="w-full max-w-2xl rounded-2xl border border-slate-200 p-0 shadow-2xl backdrop:bg-slate-900/40">
        <form id="manage-participants-form" method="POST" class="space-y-4 p-5">
            @csrf
            @method('PUT')

            <header class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold">Kelola Siswa Magang</h2>
                    <p id="manage-participants-period-name" class="text-xs text-slate-500">-</p>
                </div>

                <button type="button" onclick="document.getElementById('manage-participants-modal').close()" class="rounded-lg border border-slate-300 px-2 py-1 text-xs">Tutup</button>
            </header>

            <p class="text-xs text-slate-500">Pilih siswa yang benar-benar mengikuti period magang ini. User di luar list tidak akan dianggap aktif di menu intern.</p>

            <div id="manage-participants-list" class="max-h-72 space-y-2 overflow-auto rounded-xl border border-slate-200 bg-slate-50 p-3">
                <p class="text-sm text-slate-500">Pilih period terlebih dahulu.</p>
            </div>

            <button type="submit" class="w-full rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">Simpan List Siswa</button>
        </form>
    </dialog>

    <script>
        const internOptionsByInstitution = @json($internOptionsByInstitution);

        function renderCreatePeriodInternOptions(institutionId) {
            const select = document.getElementById('create-period-interns');
            if (!select) {
                return;
            }

            const interns = internOptionsByInstitution[String(institutionId)] || internOptionsByInstitution[Number(institutionId)] || [];
            const selectedValues = new Set(Array.from(select.selectedOptions).map((option) => Number(option.value)));

            select.innerHTML = '';

            if (!institutionId || interns.length === 0) {
                const emptyOption = document.createElement('option');
                emptyOption.value = '';
                emptyOption.textContent = institutionId ? 'Belum ada user intern untuk institusi ini' : 'Pilih institusi dulu untuk menampilkan list siswa';
                select.appendChild(emptyOption);
                return;
            }

            interns.forEach((intern) => {
                const option = document.createElement('option');
                option.value = String(intern.id);
                option.textContent = `${intern.name} (${intern.email})`;
                option.selected = selectedValues.has(Number(intern.id));
                select.appendChild(option);
            });
        }

        function openManageParticipantsModal(button) {
            const form = document.getElementById('manage-participants-form');
            const list = document.getElementById('manage-participants-list');
            const periodName = document.getElementById('manage-participants-period-name');
            const modal = document.getElementById('manage-participants-modal');

            if (!form || !list || !periodName || !modal) {
                return;
            }

            form.action = button.dataset.participantAction || '';
            periodName.textContent = button.dataset.periodName || '-';

            const institutionId = button.dataset.institutionId || '';
            const interns = internOptionsByInstitution[String(institutionId)] || internOptionsByInstitution[Number(institutionId)] || [];
            const selectedIds = new Set(
                String(button.dataset.participantIds || '')
                    .split(',')
                    .map((id) => Number(id.trim()))
                    .filter((id) => Number.isFinite(id) && id > 0)
            );

            list.innerHTML = '';

            if (interns.length === 0) {
                list.innerHTML = '<p class="text-sm text-slate-500">Belum ada user intern di institusi ini.</p>';
                modal.showModal();
                return;
            }

            interns.forEach((intern) => {
                const wrapper = document.createElement('label');
                wrapper.className = 'flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700';

                const checked = selectedIds.has(Number(intern.id)) ? 'checked' : '';
                wrapper.innerHTML = `
                    <input type="checkbox" name="intern_ids[]" value="${intern.id}" class="h-4 w-4 rounded border-slate-300" ${checked}>
                    <span>${intern.name} <span class="text-xs text-slate-500">(${intern.email})</span></span>
                `;

                list.appendChild(wrapper);
            });

            modal.showModal();
        }

        function addNewUserRow() {
            const container = document.getElementById('new-user-rows');
            if (!container) {
                return;
            }

            const nextIndex = container.querySelectorAll('.new-user-row').length;
            const wrapper = document.createElement('div');
            wrapper.className = 'new-user-row grid gap-2 rounded-xl border border-slate-200 bg-slate-50 p-3 md:grid-cols-[1fr_1fr_auto]';
            wrapper.dataset.index = String(nextIndex);
            wrapper.innerHTML = `
                <input type="text" name="new_users[${nextIndex}][name]" placeholder="Nama Intern" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input type="email" name="new_users[${nextIndex}][email]" placeholder="intern@institusi.ac.id" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <button type="button" class="rounded-lg border border-rose-300 px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-50" onclick="removeNewUserRow(this)">Hapus</button>
            `;

            container.appendChild(wrapper);
        }

        function removeNewUserRow(button) {
            const row = button.closest('.new-user-row');
            if (!row) {
                return;
            }

            const container = document.getElementById('new-user-rows');
            const rows = container ? container.querySelectorAll('.new-user-row') : [];
            if (rows.length <= 1) {
                row.querySelectorAll('input').forEach((input) => {
                    input.value = '';
                });
                return;
            }

            row.remove();
        }

        function openEditPeriodModal(button) {
            document.getElementById('edit-period-form').action = button.dataset.editAction;
            document.getElementById('edit-period-institution').value = button.dataset.institutionId || '';
            document.getElementById('edit-period-name').value = button.dataset.name || '';
            document.getElementById('edit-period-start').value = button.dataset.start || '';
            document.getElementById('edit-period-end').value = button.dataset.end || '';
            document.getElementById('edit-period-holidays').value = button.dataset.holidays || '';
            document.getElementById('edit-period-modal').showModal();
        }

        (function initCreatePeriodInternSelector() {
            const institutionSelect = document.getElementById('create-period-institution');
            if (!institutionSelect) {
                return;
            }

            renderCreatePeriodInternOptions(institutionSelect.value);
            institutionSelect.addEventListener('change', (event) => {
                renderCreatePeriodInternOptions(event.target.value);
            });
        })();
    </script>
@endsection
