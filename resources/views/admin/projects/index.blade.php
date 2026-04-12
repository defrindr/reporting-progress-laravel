@extends('layouts.app')

@section('content')
    <section class="space-y-6">
        <header class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Projects</h1>
                <p class="text-sm text-slate-500">Flow baru: buat project lalu kelola backlog dan sprint di halaman detail project.</p>
            </div>

            <button type="button" onclick="document.getElementById('create-project-modal').showModal()" class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">Tambah Project</button>
        </header>

        <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <form method="GET" action="{{ route('admin.projects.index') }}" class="grid gap-3 xl:grid-cols-[1fr_260px_170px_120px_auto_auto]">
                <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Cari nama/deskripsi project..." class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">

                <select name="intern_id" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                    <option value="">Semua Intern</option>
                    @foreach ($interns as $intern)
                        <option value="{{ $intern->id }}" @selected((int) ($filters['intern_id'] ?? 0) === (int) $intern->id)>
                            {{ $intern->name }}
                        </option>
                    @endforeach
                </select>

                <select name="sort" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                    <option value="created_at" @selected(($filters['sort'] ?? '') === 'created_at')>Sort: Created</option>
                    <option value="title" @selected(($filters['sort'] ?? '') === 'title')>Sort: Title</option>
                    <option value="backlogs_count" @selected(($filters['sort'] ?? '') === 'backlogs_count')>Sort: Backlog Count</option>
                </select>

                <select name="direction" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                    <option value="desc" @selected(($filters['direction'] ?? '') === 'desc')>DESC</option>
                    <option value="asc" @selected(($filters['direction'] ?? '') === 'asc')>ASC</option>
                </select>

                <button type="submit" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold hover:bg-slate-50">Terapkan</button>
                <a href="{{ route('admin.projects.index') }}" class="rounded-xl border border-slate-300 px-4 py-2.5 text-center text-sm hover:bg-slate-50">Reset</a>
            </form>
        </article>

        <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-slate-600">
                    <tr>
                        <th class="px-4 py-3">No</th>
                        <th class="px-4 py-3">Project</th>
                        <th class="px-4 py-3">Backlog Count</th>
                        <th class="px-4 py-3">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($specs as $project)
                        <tr>
                            <td class="px-4 py-3 align-top">{{ ($specs->firstItem() ?? 0) + $loop->index }}</td>
                            <td class="px-4 py-3">
                                <p class="font-semibold">{{ $project->title }}</p>
                                <p class="mt-1 line-clamp-2 text-xs text-slate-500">{{ $project->specification }}</p>
                                <p class="mt-1 text-xs text-slate-400">Created by: {{ $project->creator?->name ?? '-' }}</p>
                            </td>
                            <td class="px-4 py-3 align-top text-xs font-semibold text-slate-700">
                                {{ $project->backlogs_count }}
                            </td>
                            <td class="px-4 py-3 align-top">
                                <div class="flex flex-wrap gap-2">
                                    <a href="{{ route('admin.projects.show', $project) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold hover:bg-slate-50">Detail</a>

                                    <button
                                        type="button"
                                        class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold hover:bg-slate-50"
                                        data-edit-action="{{ route('admin.projects.update', $project) }}"
                                        data-title="{{ $project->title }}"
                                        data-description="{{ $project->specification }}"
                                        data-intern-ids="{{ $project->assignedInterns->pluck('id')->implode('|') }}"
                                        onclick="openEditProjectModal(this)">
                                        Edit
                                    </button>

                                    <form method="POST" action="{{ route('admin.projects.destroy', $project) }}" onsubmit="return confirm('Hapus project ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="rounded-lg bg-rose-600 px-3 py-2 text-xs font-semibold text-white hover:bg-rose-500">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-6 text-center text-sm text-slate-500">Belum ada project.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>
            {{ $specs->links() }}
        </div>
    </section>

    <dialog id="create-project-modal" class="w-full max-w-3xl rounded-2xl border border-slate-200 p-0 shadow-2xl backdrop:bg-slate-900/40">
        <form method="POST" action="{{ route('admin.projects.store') }}" class="space-y-4 p-5">
            @csrf

            <header class="flex items-center justify-between">
                <h2 class="text-lg font-semibold">Tambah Project</h2>
                <button type="button" onclick="document.getElementById('create-project-modal').close()" class="rounded-lg border border-slate-300 px-2 py-1 text-xs">Tutup</button>
            </header>

            <input name="name" type="text" required placeholder="Nama project" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
            <textarea name="description" rows="4" required placeholder="Deskripsi project" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"></textarea>

            <div>
                <p class="mb-2 text-xs font-medium text-slate-500">Assign Intern Awal (opsional)</p>
                <select name="intern_ids[]" multiple class="h-56 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                    @foreach ($interns as $intern)
                        <option value="{{ $intern->id }}">{{ $intern->name }}</option>
                    @endforeach
                </select>
            </div>

            <button type="submit" class="w-full rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">Simpan Project</button>
        </form>
    </dialog>

    <dialog id="edit-project-modal" class="w-full max-w-3xl rounded-2xl border border-slate-200 p-0 shadow-2xl backdrop:bg-slate-900/40">
        <form id="edit-project-form" method="POST" class="space-y-4 p-5">
            @csrf
            @method('PUT')

            <header class="flex items-center justify-between">
                <h2 class="text-lg font-semibold">Edit Project</h2>
                <button type="button" onclick="document.getElementById('edit-project-modal').close()" class="rounded-lg border border-slate-300 px-2 py-1 text-xs">Tutup</button>
            </header>

            <input id="edit-project-name" name="name" type="text" required class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
            <textarea id="edit-project-description" name="description" rows="4" required class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"></textarea>

            <div>
                <p class="mb-2 text-xs font-medium text-slate-500">Assign Intern Awal (opsional)</p>
                <select id="edit-project-interns" name="intern_ids[]" multiple class="h-56 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                    @foreach ($interns as $intern)
                        <option value="{{ $intern->id }}">{{ $intern->name }}</option>
                    @endforeach
                </select>
            </div>

            <button type="submit" class="w-full rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">Update Project</button>
        </form>
    </dialog>

    <script>
        function openEditProjectModal(button) {
            const modal = document.getElementById('edit-project-modal');
            const form = document.getElementById('edit-project-form');
            const name = document.getElementById('edit-project-name');
            const description = document.getElementById('edit-project-description');
            const internSelect = document.getElementById('edit-project-interns');

            form.action = button.dataset.editAction;
            name.value = button.dataset.title || '';
            description.value = button.dataset.description || '';

            const selectedInternIds = (button.dataset.internIds || '').split('|').filter(Boolean);
            Array.from(internSelect.options).forEach((option) => {
                option.selected = selectedInternIds.includes(option.value);
            });

            modal.showModal();
        }
    </script>
@endsection
