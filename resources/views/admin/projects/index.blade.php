@extends('layouts.app')

@section('content')
    <section class="space-y-6">
        <header class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Project Specs & Monitoring</h1>
                <p class="text-sm text-slate-500">Admin membuat project spec, assign ke intern, dan memonitor task harian.</p>
            </div>

            <button type="button" onclick="document.getElementById('create-spec-modal').showModal()" class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">Tambah Project Spec</button>
        </header>

        <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-slate-600">
                    <tr>
                        <th class="px-4 py-3">ID</th>
                        <th class="px-4 py-3">Project Spec</th>
                        <th class="px-4 py-3">Intern Assigned</th>
                        <th class="px-4 py-3">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($specs as $spec)
                        <tr>
                            <td class="px-4 py-3 align-top">{{ $spec->id }}</td>
                            <td class="px-4 py-3">
                                <p class="font-semibold">{{ $spec->title }}</p>
                                <p class="mt-1 line-clamp-2 text-xs text-slate-500">{{ $spec->specification }}</p>
                            </td>
                            <td class="px-4 py-3 align-top text-xs text-slate-600">
                                {{ $spec->assignedInterns->pluck('name')->implode(', ') ?: '-' }}
                            </td>
                            <td class="px-4 py-3 align-top">
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold hover:bg-slate-50"
                                        data-edit-action="{{ route('admin.projects.update', $spec) }}"
                                        data-title="{{ $spec->title }}"
                                        data-specification="{{ $spec->specification }}"
                                        data-intern-ids="{{ $spec->assignedInterns->pluck('id')->implode('|') }}"
                                        onclick="openEditSpecModal(this)">
                                        Edit
                                    </button>

                                    <form method="POST" action="{{ route('admin.projects.destroy', $spec) }}" onsubmit="return confirm('Hapus project spec ini?')">
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
            {{ $specs->links() }}
        </div>

        <section class="space-y-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <header class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold">Monitoring Task Harian Intern</h2>
                    <p class="text-sm text-slate-500">Admin/Supervisor memantau task berdasarkan tanggal pembuatan.</p>
                </div>

                <form method="GET" action="{{ route('admin.projects.index') }}" class="flex items-center gap-2 text-sm">
                    <input type="date" name="task_date" value="{{ $taskDate }}" class="rounded-lg border border-slate-300 px-3 py-2">
                    <button type="submit" class="rounded-lg border border-slate-300 px-3 py-2 hover:bg-slate-50">Filter</button>
                    @if ($taskDate)
                        <a href="{{ route('admin.projects.index') }}" class="rounded-lg border border-slate-300 px-3 py-2 hover:bg-slate-50">Reset</a>
                    @endif
                </form>
            </header>

            <div class="overflow-x-auto rounded-xl border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-slate-600">
                        <tr>
                            <th class="px-3 py-2">Tanggal</th>
                            <th class="px-3 py-2">Intern</th>
                            <th class="px-3 py-2">Task</th>
                            <th class="px-3 py-2">Project Spec</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2">Creator</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($tasks as $task)
                            <tr>
                                <td class="px-3 py-2">{{ optional($task->created_at)->toDateString() }}</td>
                                <td class="px-3 py-2">{{ $task->assignee?->name ?? '-' }}</td>
                                <td class="px-3 py-2">
                                    <p class="font-medium">{{ $task->title }}</p>
                                    <p class="text-xs text-slate-500">{{ $task->description ?: '-' }}</p>
                                </td>
                                <td class="px-3 py-2">{{ $task->spec?->title ?? '-' }}</td>
                                <td class="px-3 py-2">
                                    <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-medium uppercase">{{ $task->status }}</span>
                                </td>
                                <td class="px-3 py-2">{{ $task->creator?->name ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-3 py-6 text-center text-sm text-slate-500">Belum ada task untuk filter tanggal ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div>
                {{ $tasks->links() }}
            </div>
        </section>
    </section>

    <dialog id="create-spec-modal" class="w-full max-w-3xl rounded-2xl border border-slate-200 p-0 shadow-2xl backdrop:bg-slate-900/40">
        <form method="POST" action="{{ route('admin.projects.store') }}" class="space-y-4 p-5">
            @csrf

            <header class="flex items-center justify-between">
                <h2 class="text-lg font-semibold">Tambah Project Spec</h2>
                <button type="button" onclick="document.getElementById('create-spec-modal').close()" class="rounded-lg border border-slate-300 px-2 py-1 text-xs">Tutup</button>
            </header>

            <input name="title" type="text" required placeholder="Judul project spec" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
            <textarea name="specification" rows="4" required placeholder="Spesifikasi detail project" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"></textarea>

            <div>
                <p class="mb-2 text-xs font-medium text-slate-500">Assign Intern (bisa multi pilih)</p>
                <select name="intern_ids[]" multiple required class="h-56 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                    @foreach ($interns as $intern)
                        <option value="{{ $intern->id }}">{{ $intern->name }}</option>
                    @endforeach
                </select>
            </div>

            <button type="submit" class="w-full rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">Simpan Project Spec</button>
        </form>
    </dialog>

    <dialog id="edit-spec-modal" class="w-full max-w-3xl rounded-2xl border border-slate-200 p-0 shadow-2xl backdrop:bg-slate-900/40">
        <form id="edit-spec-form" method="POST" class="space-y-4 p-5">
            @csrf
            @method('PUT')

            <header class="flex items-center justify-between">
                <h2 class="text-lg font-semibold">Edit Project Spec</h2>
                <button type="button" onclick="document.getElementById('edit-spec-modal').close()" class="rounded-lg border border-slate-300 px-2 py-1 text-xs">Tutup</button>
            </header>

            <input id="edit-spec-title" name="title" type="text" required class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
            <textarea id="edit-spec-specification" name="specification" rows="4" required class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"></textarea>

            <div>
                <p class="mb-2 text-xs font-medium text-slate-500">Assign Intern (bisa multi pilih)</p>
                <select id="edit-spec-interns" name="intern_ids[]" multiple required class="h-56 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                    @foreach ($interns as $intern)
                        <option value="{{ $intern->id }}">{{ $intern->name }}</option>
                    @endforeach
                </select>
            </div>

            <button type="submit" class="w-full rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">Update Project Spec</button>
        </form>
    </dialog>

    <script>
        function openEditSpecModal(button) {
            const modal = document.getElementById('edit-spec-modal');
            const form = document.getElementById('edit-spec-form');
            const title = document.getElementById('edit-spec-title');
            const specification = document.getElementById('edit-spec-specification');
            const internSelect = document.getElementById('edit-spec-interns');

            form.action = button.dataset.editAction;
            title.value = button.dataset.title || '';
            specification.value = button.dataset.specification || '';

            const selectedInternIds = (button.dataset.internIds || '').split('|').filter(Boolean);
            Array.from(internSelect.options).forEach((option) => {
                option.selected = selectedInternIds.includes(option.value);
            });

            modal.showModal();
        }
    </script>
@endsection
