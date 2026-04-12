@extends('layouts.app')

@section('content')
    <section class="space-y-6">
        <header class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">CRUD Institutions</h1>
                <p class="text-sm text-slate-500">Kelola daftar universitas dan sekolah vokasi.</p>
            </div>

            <button type="button" onclick="document.getElementById('create-institution-modal').showModal()" class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">Tambah Institution</button>
        </header>

        <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-slate-600">
                    <tr>
                        <th class="px-4 py-3">ID</th>
                        <th class="px-4 py-3">Data Institution</th>
                        <th class="px-4 py-3">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($institutions as $institution)
                        <tr>
                            <td class="px-4 py-3">{{ $institution->id }}</td>
                            <td class="px-4 py-3">
                                <p class="font-semibold">{{ $institution->name }}</p>
                                <p class="text-xs uppercase text-slate-500">{{ $institution->type }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold hover:bg-slate-50"
                                        data-edit-action="{{ route('admin.institutions.update', $institution) }}"
                                        data-name="{{ $institution->name }}"
                                        data-type="{{ $institution->type }}"
                                        onclick="openEditInstitutionModal(this)">
                                        Edit
                                    </button>

                                    <form method="POST" action="{{ route('admin.institutions.destroy', $institution) }}" onsubmit="return confirm('Hapus institution ini?')" class="mt-2">
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
            {{ $institutions->links() }}
        </div>
    </section>

    <dialog id="create-institution-modal" class="w-full max-w-md rounded-2xl border border-slate-200 p-0 shadow-2xl backdrop:bg-slate-900/40">
        <form method="POST" action="{{ route('admin.institutions.store') }}" class="space-y-4 p-5">
            @csrf

            <header class="flex items-center justify-between">
                <h2 class="text-lg font-semibold">Tambah Institution</h2>
                <button type="button" onclick="document.getElementById('create-institution-modal').close()" class="rounded-lg border border-slate-300 px-2 py-1 text-xs">Tutup</button>
            </header>

            <input name="name" type="text" required placeholder="Nama institusi" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
            <select name="type" required class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                <option value="university">university</option>
                <option value="vocational">vocational</option>
            </select>

            <button type="submit" class="w-full rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">Simpan</button>
        </form>
    </dialog>

    <dialog id="edit-institution-modal" class="w-full max-w-md rounded-2xl border border-slate-200 p-0 shadow-2xl backdrop:bg-slate-900/40">
        <form id="edit-institution-form" method="POST" class="space-y-4 p-5">
            @csrf
            @method('PUT')

            <header class="flex items-center justify-between">
                <h2 class="text-lg font-semibold">Edit Institution</h2>
                <button type="button" onclick="document.getElementById('edit-institution-modal').close()" class="rounded-lg border border-slate-300 px-2 py-1 text-xs">Tutup</button>
            </header>

            <input id="edit-institution-name" name="name" type="text" required class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
            <select id="edit-institution-type" name="type" required class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                <option value="university">university</option>
                <option value="vocational">vocational</option>
            </select>

            <button type="submit" class="w-full rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">Update</button>
        </form>
    </dialog>

    <script>
        function openEditInstitutionModal(button) {
            const modal = document.getElementById('edit-institution-modal');
            document.getElementById('edit-institution-form').action = button.dataset.editAction;
            document.getElementById('edit-institution-name').value = button.dataset.name || '';
            document.getElementById('edit-institution-type').value = button.dataset.type || 'university';
            modal.showModal();
        }
    </script>
@endsection
