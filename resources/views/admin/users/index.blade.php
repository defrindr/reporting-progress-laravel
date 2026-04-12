@extends('layouts.app')

@section('content')
    <section class="space-y-6">
        <header class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">CRUD Users</h1>
                <p class="text-sm text-slate-500">Manajemen akun Admin, Supervisor, dan Intern lewat popup form.</p>
            </div>

            <button type="button" onclick="document.getElementById('create-user-modal').showModal()" class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">Tambah User</button>
        </header>

        <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <form method="GET" action="{{ route('admin.users.index') }}" class="grid gap-3 xl:grid-cols-[1.2fr_180px_220px_170px_120px_auto_auto]">
                <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Cari nama atau email user..." class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">

                <select name="role" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                    <option value="">Semua Role</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role->name }}" @selected(($filters['role'] ?? '') === $role->name)>{{ $role->name }}</option>
                    @endforeach
                </select>

                <select name="institution_id" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                    <option value="">Semua Institution</option>
                    @foreach ($institutions as $institution)
                        <option value="{{ $institution->id }}" @selected((int) ($filters['institution_id'] ?? 0) === (int) $institution->id)>
                            {{ $institution->name }} ({{ $institution->type }})
                        </option>
                    @endforeach
                </select>

                <select name="sort" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                    <option value="created_at" @selected(($filters['sort'] ?? '') === 'created_at')>Sort: Created</option>
                    <option value="name" @selected(($filters['sort'] ?? '') === 'name')>Sort: Name</option>
                    <option value="email" @selected(($filters['sort'] ?? '') === 'email')>Sort: Email</option>
                </select>

                <select name="direction" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                    <option value="desc" @selected(($filters['direction'] ?? '') === 'desc')>DESC</option>
                    <option value="asc" @selected(($filters['direction'] ?? '') === 'asc')>ASC</option>
                </select>

                <button type="submit" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold hover:bg-slate-50 dark:hover:bg-slate-700">Terapkan</button>
                <a href="{{ route('admin.users.index') }}" class="rounded-xl border border-slate-300 px-4 py-2.5 text-center text-sm hover:bg-slate-50 dark:hover:bg-slate-700">Reset</a>
            </form>
        </article>

        <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-slate-600">
                    <tr>
                        <th class="px-4 py-3">No</th>
                        <th class="px-4 py-3">Data User</th>
                        <th class="px-4 py-3">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($users as $user)
                        <tr>
                            <td class="px-4 py-3 align-top">{{ ($users->firstItem() ?? 0) + $loop->index }}</td>
                            <td class="px-4 py-3">
                                <p class="font-semibold">{{ $user->name }}</p>
                                <p class="text-xs text-slate-500">{{ $user->email }}</p>
                                <p class="mt-1 text-xs text-slate-500">Institution: {{ $user->institution?->name ?? '-' }}</p>
                                <p class="mt-1 text-xs text-slate-500">Roles: {{ $user->roles->pluck('name')->implode(', ') ?: '-' }}</p>
                            </td>
                            <td class="px-4 py-3 align-top">
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold hover:bg-slate-50 dark:hover:bg-slate-700"
                                        data-edit-action="{{ route('admin.users.update', $user) }}"
                                        data-name="{{ $user->name }}"
                                        data-email="{{ $user->email }}"
                                        data-institution-id="{{ $user->institution_id ?? '' }}"
                                        data-roles="{{ $user->roles->pluck('name')->implode('|') }}"
                                        onclick="openEditUserModal(this)">
                                        Edit
                                    </button>

                                    <form method="POST" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('Hapus user ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="rounded-lg bg-rose-600 px-3 py-2 text-xs font-semibold text-white hover:bg-rose-500">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-4 py-6 text-center text-sm text-slate-500">Tidak ada user ditemukan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>
            {{ $users->links() }}
        </div>
    </section>

    <dialog id="create-user-modal" class="w-full max-w-3xl rounded-2xl border border-slate-200 p-0 shadow-2xl backdrop:bg-slate-900/40">
        <form method="POST" action="{{ route('admin.users.store') }}" class="space-y-4 p-5">
            @csrf

            <header class="flex items-center justify-between">
                <h2 class="text-lg font-semibold">Tambah User</h2>
                <button type="button" onclick="document.getElementById('create-user-modal').close()" class="rounded-lg border border-slate-300 px-2 py-1 text-xs">Tutup</button>
            </header>

            <div class="grid gap-3 lg:grid-cols-3">
                <input name="name" type="text" required placeholder="Nama" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                <input name="email" type="email" required placeholder="Email" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                <input name="password" type="password" required placeholder="Password min 8" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
            </div>

            <select name="institution_id" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                <option value="">Pilih Institution</option>
                @foreach ($institutions as $institution)
                    <option value="{{ $institution->id }}">{{ $institution->name }} ({{ $institution->type }})</option>
                @endforeach
            </select>

            <div class="rounded-xl border border-slate-300 px-3 py-2">
                <p class="mb-1 text-xs font-medium text-slate-500">Roles</p>
                <div class="flex flex-wrap gap-3 text-sm">
                    @foreach ($roles as $role)
                        <label class="inline-flex items-center gap-1.5">
                            <input type="checkbox" name="roles[]" value="{{ $role->name }}" class="rounded border-slate-300">
                            {{ $role->name }}
                        </label>
                    @endforeach
                </div>
            </div>

            <button type="submit" class="w-full rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">Simpan User</button>
        </form>
    </dialog>

    <dialog id="edit-user-modal" class="w-full max-w-3xl rounded-2xl border border-slate-200 p-0 shadow-2xl backdrop:bg-slate-900/40">
        <form id="edit-user-form" method="POST" class="space-y-4 p-5">
            @csrf
            @method('PUT')

            <header class="flex items-center justify-between">
                <h2 class="text-lg font-semibold">Edit User</h2>
                <button type="button" onclick="document.getElementById('edit-user-modal').close()" class="rounded-lg border border-slate-300 px-2 py-1 text-xs">Tutup</button>
            </header>

            <div class="grid gap-3 lg:grid-cols-3">
                <input id="edit-user-name" name="name" type="text" required class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                <input id="edit-user-email" name="email" type="email" required class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                <input name="password" type="password" placeholder="Kosongkan jika tidak ganti" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
            </div>

            <select id="edit-user-institution" name="institution_id" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                <option value="">Pilih Institution</option>
                @foreach ($institutions as $institution)
                    <option value="{{ $institution->id }}">{{ $institution->name }} ({{ $institution->type }})</option>
                @endforeach
            </select>

            <div class="rounded-xl border border-slate-300 px-3 py-2">
                <p class="mb-1 text-xs font-medium text-slate-500">Roles</p>
                <div class="flex flex-wrap gap-3 text-sm">
                    @foreach ($roles as $role)
                        <label class="inline-flex items-center gap-1.5">
                            <input type="checkbox" name="roles[]" value="{{ $role->name }}" class="edit-user-role rounded border-slate-300">
                            {{ $role->name }}
                        </label>
                    @endforeach
                </div>
            </div>

            <button type="submit" class="w-full rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">Update User</button>
        </form>
    </dialog>

    <script>
        function openEditUserModal(button) {
            const modal = document.getElementById('edit-user-modal');
            const form = document.getElementById('edit-user-form');

            form.action = button.dataset.editAction;
            document.getElementById('edit-user-name').value = button.dataset.name || '';
            document.getElementById('edit-user-email').value = button.dataset.email || '';
            document.getElementById('edit-user-institution').value = button.dataset.institutionId || '';

            const selectedRoles = (button.dataset.roles || '').split('|').filter(Boolean);
            document.querySelectorAll('.edit-user-role').forEach((checkbox) => {
                checkbox.checked = selectedRoles.includes(checkbox.value);
            });

            modal.showModal();
        }
    </script>
@endsection
