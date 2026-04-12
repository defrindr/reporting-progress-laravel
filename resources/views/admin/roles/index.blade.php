@extends('layouts.app')

@section('content')
    <section class="space-y-6">
        <header class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">CRUD Roles</h1>
                <p class="text-sm text-slate-500">Manajemen role sistem dengan popup form tambah/edit.</p>
            </div>

            <button type="button" onclick="document.getElementById('create-role-modal').showModal()" class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">Tambah Role</button>
        </header>

        <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <form method="GET" action="{{ route('admin.roles.index') }}" class="grid gap-3 lg:grid-cols-[1fr_180px_140px_auto_auto]">
                <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Cari nama role..." class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">

                <select name="sort" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                    <option value="name" @selected(($filters['sort'] ?? '') === 'name')>Sort: Name</option>
                    <option value="created_at" @selected(($filters['sort'] ?? '') === 'created_at')>Sort: Created</option>
                    <option value="updated_at" @selected(($filters['sort'] ?? '') === 'updated_at')>Sort: Updated</option>
                </select>

                <select name="direction" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                    <option value="asc" @selected(($filters['direction'] ?? '') === 'asc')>ASC</option>
                    <option value="desc" @selected(($filters['direction'] ?? '') === 'desc')>DESC</option>
                </select>

                <button type="submit" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold hover:bg-slate-50">Terapkan</button>
                <a href="{{ route('admin.roles.index') }}" class="rounded-xl border border-slate-300 px-4 py-2.5 text-center text-sm hover:bg-slate-50">Reset</a>
            </form>
        </article>

        <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-slate-600">
                    <tr>
                        <th class="px-4 py-3">No</th>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($roles as $role)
                        <tr>
                            <td class="px-4 py-3">{{ ($roles->firstItem() ?? 0) + $loop->index }}</td>
                            <td class="px-4 py-3">{{ $role->name }}</td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold hover:bg-slate-50"
                                        data-role-id="{{ $role->id }}"
                                        data-role-name="{{ $role->name }}"
                                        data-role-action="{{ route('admin.roles.update', $role) }}"
                                        onclick="openEditRoleModal(this)">
                                        Edit
                                    </button>

                                    <form method="POST" action="{{ route('admin.roles.destroy', $role) }}" onsubmit="return confirm('Hapus role ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="rounded-lg bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-rose-500">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div>
            {{ $roles->links() }}
        </div>
    </section>

    <dialog id="create-role-modal" class="w-full max-w-md rounded-2xl border border-slate-200 p-0 shadow-2xl backdrop:bg-slate-900/40">
        <form method="POST" action="{{ route('admin.roles.store') }}" class="space-y-4 p-5">
            @csrf
            <header class="flex items-center justify-between">
                <h2 class="text-lg font-semibold">Tambah Role</h2>
                <button type="button" onclick="document.getElementById('create-role-modal').close()" class="rounded-lg border border-slate-300 px-2 py-1 text-xs">Tutup</button>
            </header>

            <input name="name" type="text" required placeholder="Nama role (contoh: Supervisor)" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-slate-900">

            <button type="submit" class="w-full rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">Simpan</button>
        </form>
    </dialog>

    <dialog id="edit-role-modal" class="w-full max-w-md rounded-2xl border border-slate-200 p-0 shadow-2xl backdrop:bg-slate-900/40">
        <form id="edit-role-form" method="POST" class="space-y-4 p-5">
            @csrf
            @method('PUT')

            <header class="flex items-center justify-between">
                <h2 class="text-lg font-semibold">Edit Role</h2>
                <button type="button" onclick="document.getElementById('edit-role-modal').close()" class="rounded-lg border border-slate-300 px-2 py-1 text-xs">Tutup</button>
            </header>

            <input id="edit-role-name" name="name" type="text" required class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-slate-900">

            <button type="submit" class="w-full rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">Update</button>
        </form>
    </dialog>

    <script>
        function openEditRoleModal(button) {
            const modal = document.getElementById('edit-role-modal');
            const form = document.getElementById('edit-role-form');
            const nameInput = document.getElementById('edit-role-name');

            form.action = button.dataset.roleAction;
            nameInput.value = button.dataset.roleName || '';

            modal.showModal();
        }
    </script>
@endsection
