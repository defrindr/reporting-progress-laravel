@extends('layouts.app')

@section('content')
    <section class="space-y-6">
        <header class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Kelola Hari Libur</h1>
                <p class="text-sm text-slate-500">Kelola hari libur nasional (sinkron) dan hari libur perusahaan (manual).</p>
            </div>

            <button type="button" onclick="document.getElementById('create-holiday-modal').showModal()" class="btn-primary">
                + Tambah Hari Libur Perusahaan
            </button>
        </header>

        <div class="flex flex-wrap items-center gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <form method="GET" action="{{ route('admin.holidays.index') }}" class="flex flex-wrap items-center gap-3">
                <input type="number" name="year" min="2000" max="2100" value="{{ $year }}" class="w-28 rounded-xl border border-slate-300 px-3 py-2 text-sm">

                <select name="country_code" class="rounded-xl border border-slate-300 px-3 py-2 text-sm">
                    @foreach ($holidayCountries as $code => $name)
                        <option value="{{ $code }}" @selected($countryCode === $code)>{{ $name }}</option>
                    @endforeach
                </select>

                <button type="submit" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold btn-ghost">
                    Terapkan
                </button>
            </form>

            <form method="POST" action="{{ route('admin.holidays.sync') }}" class="flex flex-wrap items-center gap-2 ml-auto">
                @csrf
                <input type="hidden" name="year" value="{{ $year }}">
                <input type="hidden" name="country_code" value="{{ $countryCode }}">
                <button type="submit" class="btn-primary">
                    Sync dari Sumber Eksternal
                </button>
            </form>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('admin.holidays.index', ['year' => $year, 'country_code' => $countryCode, 'type' => 'all']) }}"
                class="rounded-lg px-3 py-1.5 text-sm font-medium {{ $type === 'all' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                Semua ({{ $holidays->total() }})
            </a>
            <a href="{{ route('admin.holidays.index', ['year' => $year, 'country_code' => $countryCode, 'type' => 'synced']) }}"
                class="rounded-lg px-3 py-1.5 text-sm font-medium {{ $type === 'synced' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                Libur Nasional
            </a>
            <a href="{{ route('admin.holidays.index', ['year' => $year, 'country_code' => $countryCode, 'type' => 'company']) }}"
                class="rounded-lg px-3 py-1.5 text-sm font-medium {{ $type === 'company' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                Libur Perusahaan
            </a>
        </div>

        <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-slate-600">
                    <tr>
                        <th class="px-4 py-3">Tanggal</th>
                        <th class="px-4 py-3">Nama</th>
                        <th class="px-4 py-3">Tipe</th>
                        <th class="px-4 py-3">Sumber</th>
                        <th class="px-4 py-3">Deskripsi</th>
                        <th class="px-4 py-3">Dibuat oleh</th>
                        <th class="px-4 py-3">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($holidays as $holiday)
                        <tr>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="font-medium">{{ optional($holiday->holiday_date)->toDateString() }}</span>
                            </td>
                            <td class="px-4 py-3">{{ $holiday->name }}</td>
                            <td class="px-4 py-3">
                                @if ($holiday->is_company_holiday)
                                    <span class="rounded-full bg-emerald-100 px-2 py-1 text-xs font-medium text-emerald-800">Perusahaan</span>
                                @else
                                    <span class="rounded-full bg-blue-100 px-2 py-1 text-xs font-medium text-blue-800">Nasional</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-500">
                                @if ($holiday->source === 'nager')
                                    Nager API
                                @elseif ($holiday->source === 'libur.deno.dev')
                                    Libur Deno
                                @elseif ($holiday->source === 'manual')
                                    Manual
                                @else
                                    {{ $holiday->source ?? '-' }}
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-500">
                                {{ $holiday->description ?? '-' }}
                            </td>
                            <td class="px-4 py-3 text-slate-500">
                                {{ $holiday->creator->name ?? '-' }}
                            </td>
                            <td class="px-4 py-3">
                                @if ($holiday->is_company_holiday)
                                    <div class="flex flex-wrap gap-2">
                                        <button
                                            type="button"
                                            class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold btn-ghost"
                                            data-edit-action="{{ route('admin.holidays.update', $holiday) }}"
                                            data-holiday-date="{{ optional($holiday->holiday_date)->toDateString() }}"
                                            data-holiday-name="{{ $holiday->name }}"
                                            data-holiday-description="{{ $holiday->description }}"
                                            onclick="openEditHolidayModal(this)">
                                            Edit
                                        </button>

                                        <form method="POST" action="{{ route('admin.holidays.destroy', $holiday) }}" onsubmit="return confirm('Hapus hari libur ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded-lg bg-rose-600 px-3 py-2 text-xs font-semibold text-white hover:bg-rose-500">
                                                Hapus
                                            </button>
                                        </form>
                                    </div>
                                @else
                                    <span class="text-xs text-slate-400">Tidak bisa diedit</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-6 text-center text-sm text-slate-500">
                                Tidak ada data hari libur untuk tahun ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>
            {{ $holidays->links() }}
        </div>
    </section>

    <dialog id="create-holiday-modal" class="w-full max-w-md rounded-2xl border border-slate-200 p-0 shadow-2xl backdrop:bg-slate-900/40">
        <form method="POST" action="{{ route('admin.holidays.store') }}" class="space-y-4 p-5">
            @csrf

            <header class="flex items-center justify-between">
                <h2 class="text-lg font-semibold">Tambah Hari Libur Perusahaan</h2>
                <button type="button" onclick="document.getElementById('create-holiday-modal').close()" class="rounded-lg border border-slate-300 px-2 py-1 text-xs">Tutup</button>
            </header>

            <div class="space-y-3">
                <div>
                    <label for="create-holiday-date" class="block text-sm font-medium text-slate-700">Tanggal</label>
                    <input type="date" name="holiday_date" id="create-holiday-date" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                </div>

                <div>
                    <label for="create-holiday-name" class="block text-sm font-medium text-slate-700">Nama Hari Libur</label>
                    <input type="text" name="name" id="create-holiday-name" required placeholder="Contoh: Hari Raya Nyepi" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                </div>

                <div>
                    <label for="create-holiday-description" class="block text-sm font-medium text-slate-700">Deskripsi (Opsional)</label>
                    <textarea name="description" id="create-holiday-description" rows="2" placeholder="Keterangan tambahan..." class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"></textarea>
                </div>

                <input type="hidden" name="country_code" value="{{ $countryCode }}">
            </div>

            <button type="submit" class="btn-primary w-full">
                Simpan
            </button>
        </form>
    </dialog>

    <dialog id="edit-holiday-modal" class="w-full max-w-md rounded-2xl border border-slate-200 p-0 shadow-2xl backdrop:bg-slate-900/40">
        <form id="edit-holiday-form" method="POST" class="space-y-4 p-5">
            @csrf
            @method('PUT')

            <header class="flex items-center justify-between">
                <h2 class="text-lg font-semibold">Edit Hari Libur Perusahaan</h2>
                <button type="button" onclick="document.getElementById('edit-holiday-modal').close()" class="rounded-lg border border-slate-300 px-2 py-1 text-xs">Tutup</button>
            </header>

            <div class="space-y-3">
                <div>
                    <label for="edit-holiday-date" class="block text-sm font-medium text-slate-700">Tanggal</label>
                    <input type="date" name="holiday_date" id="edit-holiday-date" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                </div>

                <div>
                    <label for="edit-holiday-name" class="block text-sm font-medium text-slate-700">Nama Hari Libur</label>
                    <input type="text" name="name" id="edit-holiday-name" required class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                </div>

                <div>
                    <label for="edit-holiday-description" class="block text-sm font-medium text-slate-700">Deskripsi (Opsional)</label>
                    <textarea name="description" id="edit-holiday-description" rows="2" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"></textarea>
                </div>
            </div>

            <button type="submit" class="btn-primary w-full">
                Perbarui
            </button>
        </form>
    </dialog>

    <script>
        function openEditHolidayModal(button) {
            const form = document.getElementById('edit-holiday-form');
            const modal = document.getElementById('edit-holiday-modal');

            form.action = button.dataset.editAction;
            document.getElementById('edit-holiday-date').value = button.dataset.holidayDate || '';
            document.getElementById('edit-holiday-name').value = button.dataset.holidayName || '';
            document.getElementById('edit-holiday-description').value = button.dataset.holidayDescription || '';

            modal.showModal();
        }
    </script>
@endsection
