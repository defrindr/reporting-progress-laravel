@extends('layouts.app')

@section('content')
    <section class="space-y-6">
        <header class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-xs uppercase tracking-wide text-slate-500">Project Detail</p>
                <h1 class="text-2xl font-semibold tracking-tight">{{ $project->title }}</h1>
                <p class="mt-1 text-sm text-slate-500">{{ $project->specification }}</p>
                <p class="mt-1 text-xs text-slate-400">Created by: {{ $project->creator?->name ?? '-' }}</p>
            </div>

            <div class="flex gap-2">
                <a href="{{ route('admin.projects.index') }}" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm hover:bg-slate-50">Kembali</a>
                <button type="button" onclick="document.getElementById('create-backlog-modal').showModal()" class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">Tambah Backlog</button>
            </div>
        </header>

        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <form method="GET" action="{{ route('admin.projects.show', $project) }}" class="grid gap-3 lg:grid-cols-[180px_1fr_1fr_auto]">
                <select name="scope" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                    <option value="backlog" @selected($scope === 'backlog')>Backlog (belum sprint)</option>
                    <option value="sprint" @selected($scope === 'sprint')>Di Sprint</option>
                    <option value="all" @selected($scope === 'all')>Semua</option>
                </select>

                <select name="sprint_id" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                    <option value="">Semua Sprint</option>
                    @foreach ($periods as $period)
                        <option value="{{ $period->id }}" @selected($sprintId === $period->id)>
                            {{ $period->name }} ({{ $period->institution?->name ?? '-' }})
                        </option>
                    @endforeach
                </select>

                <select name="activation_period_id" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                    <option value="">Prefill Aktivasi Sprint (opsional)</option>
                    @foreach ($periods as $period)
                        <option value="{{ $period->id }}" @selected($activationPeriodId === $period->id)>
                            {{ $period->name }} ({{ $period->institution?->name ?? '-' }})
                        </option>
                    @endforeach
                </select>

                <button type="submit" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm hover:bg-slate-50">Terapkan</button>
            </form>
        </article>

        <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-slate-600">
                    <tr>
                        <th class="px-4 py-3">ID</th>
                        <th class="px-4 py-3">Task</th>
                        <th class="px-4 py-3">Assign To</th>
                        <th class="px-4 py-3">Due Date</th>
                        <th class="px-4 py-3">Priority</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Sprint</th>
                        <th class="px-4 py-3">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($backlogs as $backlog)
                        <tr>
                            <td class="px-4 py-3">{{ $backlog->id }}</td>
                            <td class="px-4 py-3">
                                <p class="font-semibold">{{ $backlog->title }}</p>
                                <p class="text-xs text-slate-500">{{ $backlog->description ?: '-' }}</p>
                            </td>
                            <td class="px-4 py-3">{{ $backlog->assignee?->name ?? '-' }}</td>
                            <td class="px-4 py-3">{{ optional($backlog->due_date)->toDateString() ?? '-' }}</td>
                            <td class="px-4 py-3"><span class="rounded-full bg-slate-100 px-2 py-1 text-xs uppercase">{{ $backlog->priority }}</span></td>
                            <td class="px-4 py-3"><span class="rounded-full bg-slate-100 px-2 py-1 text-xs uppercase">{{ $backlog->status }}</span></td>
                            <td class="px-4 py-3">{{ $backlog->sprint?->name ?? 'Backlog' }}</td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold hover:bg-slate-50"
                                        data-edit-action="{{ route('admin.projects.backlogs.update', [$project, $backlog]) }}"
                                        data-title="{{ $backlog->title }}"
                                        data-description="{{ $backlog->description }}"
                                        data-assignee-id="{{ $backlog->assignee_id }}"
                                        data-due-date="{{ optional($backlog->due_date)->toDateString() }}"
                                        data-priority="{{ $backlog->priority }}"
                                        data-status="{{ $backlog->status }}"
                                        onclick="openEditBacklogModal(this)">
                                        Edit
                                    </button>

                                    <form method="POST" action="{{ route('admin.projects.backlogs.destroy', [$project, $backlog]) }}" onsubmit="return confirm('Hapus backlog ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="rounded-lg bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-rose-500">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-6 text-center text-sm text-slate-500">Belum ada backlog.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>
            {{ $backlogs->links() }}
        </div>

        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <header class="mb-3">
                <h2 class="text-lg font-semibold">Aktifkan Sprint</h2>
                <p class="text-sm text-slate-500">Pilih sprint (period) dan backlog yang akan dinaikkan ke sprint tersebut.</p>
            </header>

            <form method="POST" action="{{ route('admin.projects.activate-sprint', $project) }}" class="space-y-4">
                @csrf
                @method('PATCH')

                <select name="period_id" required class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                    <option value="">Pilih Sprint (Period)</option>
                    @foreach ($periods as $period)
                        <option value="{{ $period->id }}" @selected($activationPeriodId === $period->id)>
                            {{ $period->name }} ({{ $period->institution?->name ?? '-' }})
                        </option>
                    @endforeach
                </select>

                <div class="max-h-72 space-y-2 overflow-auto rounded-xl border border-slate-200 p-3">
                    @forelse ($activationCandidates as $candidate)
                        <label class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 px-3 py-2 text-sm">
                            <span>
                                <span class="font-medium">{{ $candidate->title }}</span>
                                <span class="block text-xs text-slate-500">{{ $candidate->assignee?->name ?? '-' }} | {{ $candidate->priority }} | {{ $candidate->status }}</span>
                                <span class="block text-xs text-slate-400">Sprint saat ini: {{ $candidate->sprint?->name ?? 'Backlog' }}</span>
                            </span>
                            <input type="checkbox" name="backlog_ids[]" value="{{ $candidate->id }}" class="rounded border-slate-300" @checked(in_array($candidate->id, $activeBacklogIds, true))>
                        </label>
                    @empty
                        <p class="text-sm text-slate-500">Belum ada backlog untuk diaktifkan.</p>
                    @endforelse
                </div>

                <button type="submit" class="w-full rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">Aktifkan Sprint</button>
            </form>
        </article>
    </section>

    <dialog id="create-backlog-modal" class="w-full max-w-3xl rounded-2xl border border-slate-200 p-0 shadow-2xl backdrop:bg-slate-900/40">
        <form method="POST" action="{{ route('admin.projects.backlogs.store', $project) }}" class="space-y-4 p-5">
            @csrf

            <header class="flex items-center justify-between">
                <h2 class="text-lg font-semibold">Tambah Backlog</h2>
                <button type="button" onclick="document.getElementById('create-backlog-modal').close()" class="rounded-lg border border-slate-300 px-2 py-1 text-xs">Tutup</button>
            </header>

            <div class="grid gap-3 lg:grid-cols-2">
                <input name="title" type="text" required placeholder="Nama task" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm lg:col-span-2">
                <textarea name="description" rows="3" placeholder="Description" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm lg:col-span-2"></textarea>

                <select name="assignee_id" required class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                    <option value="">Assign to</option>
                    @foreach ($interns as $intern)
                        <option value="{{ $intern->id }}">{{ $intern->name }}</option>
                    @endforeach
                </select>

                <input name="due_date" type="date" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">

                <select name="priority" required class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                    <option value="low">low</option>
                    <option value="medium" selected>medium</option>
                    <option value="high">high</option>
                    <option value="critical">critical</option>
                </select>

                <select name="status" required class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                    <option value="todo">todo</option>
                    <option value="doing">doing</option>
                    <option value="done">done</option>
                </select>
            </div>

            <button type="submit" class="w-full rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">Simpan Backlog</button>
        </form>
    </dialog>

    <dialog id="edit-backlog-modal" class="w-full max-w-3xl rounded-2xl border border-slate-200 p-0 shadow-2xl backdrop:bg-slate-900/40">
        <form id="edit-backlog-form" method="POST" class="space-y-4 p-5">
            @csrf
            @method('PUT')

            <header class="flex items-center justify-between">
                <h2 class="text-lg font-semibold">Edit Backlog</h2>
                <button type="button" onclick="document.getElementById('edit-backlog-modal').close()" class="rounded-lg border border-slate-300 px-2 py-1 text-xs">Tutup</button>
            </header>

            <div class="grid gap-3 lg:grid-cols-2">
                <input id="edit-backlog-title" name="title" type="text" required class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm lg:col-span-2">
                <textarea id="edit-backlog-description" name="description" rows="3" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm lg:col-span-2"></textarea>

                <select id="edit-backlog-assignee" name="assignee_id" required class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                    @foreach ($interns as $intern)
                        <option value="{{ $intern->id }}">{{ $intern->name }}</option>
                    @endforeach
                </select>

                <input id="edit-backlog-due-date" name="due_date" type="date" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">

                <select id="edit-backlog-priority" name="priority" required class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                    <option value="low">low</option>
                    <option value="medium">medium</option>
                    <option value="high">high</option>
                    <option value="critical">critical</option>
                </select>

                <select id="edit-backlog-status" name="status" required class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                    <option value="todo">todo</option>
                    <option value="doing">doing</option>
                    <option value="done">done</option>
                </select>
            </div>

            <button type="submit" class="w-full rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">Update Backlog</button>
        </form>
    </dialog>

    <script>
        function openEditBacklogModal(button) {
            document.getElementById('edit-backlog-form').action = button.dataset.editAction;
            document.getElementById('edit-backlog-title').value = button.dataset.title || '';
            document.getElementById('edit-backlog-description').value = button.dataset.description || '';
            document.getElementById('edit-backlog-assignee').value = button.dataset.assigneeId || '';
            document.getElementById('edit-backlog-due-date').value = button.dataset.dueDate || '';
            document.getElementById('edit-backlog-priority').value = button.dataset.priority || 'medium';
            document.getElementById('edit-backlog-status').value = button.dataset.status || 'todo';
            document.getElementById('edit-backlog-modal').showModal();
        }
    </script>
@endsection
