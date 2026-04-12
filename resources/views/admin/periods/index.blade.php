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

        @if ($newUserIds->isNotEmpty())
            <article class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900">
                <p>User baru berhasil dibuat. Kamu bisa download daftar akun untuk dibagikan.</p>
                <a href="{{ route('admin.periods.new-users-csv', ['ids' => $newUserIds->implode(',')]) }}"
                    class="rounded-lg bg-blue-700 px-3 py-2 text-xs font-semibold text-white hover:bg-blue-600">
                    Download CSV User Baru
                </a>
            </article>
        @endif

        <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-slate-600">
                    <tr>
                        <th class="px-4 py-3">ID</th>
                        <th class="px-4 py-3">Data Period</th>
                        <th class="px-4 py-3">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($periods as $period)
                        <tr>
                            <td class="px-4 py-3 align-top">{{ $period->id }}</td>
                            <td class="px-4 py-3">
                                <p class="font-semibold">{{ $period->name }}</p>
                                <p class="text-xs text-slate-500">{{ optional($period->institution)->name ?? '-' }}</p>
                                <p class="text-xs text-slate-500">{{ optional($period->start_date)->toDateString() }} - {{ optional($period->end_date)->toDateString() }}</p>
                                <p class="mt-1 text-xs text-slate-500">Holidays: {{ implode(',', $period->holidays ?? []) ?: '-' }}</p>
                            </td>
                            <td class="px-4 py-3 align-top">
                                <div class="flex flex-wrap gap-2">
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
                <select name="institution_id" required class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                    <option value="">Pilih Institusi</option>
                    @foreach ($institutions as $institution)
                        <option value="{{ $institution->id }}">{{ $institution->name }} ({{ $institution->type }})</option>
                    @endforeach
                </select>

                <input name="name" type="text" required placeholder="Nama periode" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                <input name="start_date" type="date" required class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                <input name="end_date" type="date" required class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                <input name="holidays" type="text" placeholder="2026-01-01,2026-01-02" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm lg:col-span-2">

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
            </div>

            <button type="submit" class="w-full rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">Update</button>
        </form>
    </dialog>

    <script>
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
    </script>
@endsection
