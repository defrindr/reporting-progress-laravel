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
                <a href="{{ route('admin.projects.index') }}" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm hover:bg-slate-50 dark:hover:bg-slate-700">Kembali</a>
                <button type="button" onclick="document.getElementById('create-backlog-modal').showModal()" class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">Tambah Backlog</button>
            </div>
        </header>

        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <form method="GET" action="{{ route('admin.projects.show', $project) }}" class="grid gap-3 xl:grid-cols-[170px_1fr_1fr_170px_170px_200px_170px_120px_auto_auto]">
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

                <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Cari task/backlog..." class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">

                <select name="status" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                    <option value="">Semua Status</option>
                    <option value="todo" @selected(($filters['status'] ?? '') === 'todo')>todo</option>
                    <option value="doing" @selected(($filters['status'] ?? '') === 'doing')>doing</option>
                    <option value="done" @selected(($filters['status'] ?? '') === 'done')>done</option>
                </select>

                <select name="priority" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                    <option value="">Semua Priority</option>
                    <option value="low" @selected(($filters['priority'] ?? '') === 'low')>low</option>
                    <option value="medium" @selected(($filters['priority'] ?? '') === 'medium')>medium</option>
                    <option value="high" @selected(($filters['priority'] ?? '') === 'high')>high</option>
                    <option value="critical" @selected(($filters['priority'] ?? '') === 'critical')>critical</option>
                </select>

                <select name="assignee_id" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                    <option value="">Semua Assignee</option>
                    @foreach ($interns as $intern)
                        <option value="{{ $intern->id }}" @selected((int) ($filters['assignee_id'] ?? 0) === (int) $intern->id)>
                            {{ $intern->name }}
                        </option>
                    @endforeach
                </select>

                <select name="sort" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                    <option value="created_at" @selected(($filters['sort'] ?? '') === 'created_at')>Sort: Created</option>
                    <option value="title" @selected(($filters['sort'] ?? '') === 'title')>Sort: Title</option>
                    <option value="due_date" @selected(($filters['sort'] ?? '') === 'due_date')>Sort: Due Date</option>
                    <option value="priority" @selected(($filters['sort'] ?? '') === 'priority')>Sort: Priority</option>
                    <option value="status" @selected(($filters['sort'] ?? '') === 'status')>Sort: Status</option>
                </select>

                <select name="direction" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                    <option value="desc" @selected(($filters['direction'] ?? '') === 'desc')>DESC</option>
                    <option value="asc" @selected(($filters['direction'] ?? '') === 'asc')>ASC</option>
                </select>

                <button type="submit" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm hover:bg-slate-50 dark:hover:bg-slate-700">Terapkan</button>
                <a href="{{ route('admin.projects.show', [$project, 'scope' => $scope, 'sprint_id' => $sprintId ?: null]) }}" class="rounded-xl border border-slate-300 px-4 py-2.5 text-center text-sm hover:bg-slate-50 dark:hover:bg-slate-700">Reset</a>
            </form>
        </article>

        <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-slate-600">
                    <tr>
                        <th class="px-4 py-3">No</th>
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
                            <td class="px-4 py-3">{{ ($backlogs->firstItem() ?? 0) + $loop->index }}</td>
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
                                        class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold hover:bg-slate-50 dark:hover:bg-slate-700"
                                        data-edit-action="{{ route('admin.projects.backlogs.update', [$project, $backlog]) }}"
                                        data-title="{{ $backlog->title }}"
                                        data-description="{{ $backlog->description }}"
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
                <p class="text-sm text-slate-500">Pilih backlog yang akan dinaikkan ke sprint otomatis (sprint aktif saat ini), lalu tentukan assignee intern aktif untuk tiap backlog terpilih.</p>
            </header>

            @if ($interns->isEmpty())
                <div class="mb-4 rounded-xl border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-700">
                    Belum ada intern project dengan periode internship aktif. Tambahkan intern aktif dulu sebelum aktivasi sprint.
                </div>
            @endif

            @if ($activationError)
                <div class="mb-4 rounded-xl border border-rose-300 bg-rose-50 px-3 py-2 text-sm text-rose-700">
                    {{ $activationError }}
                </div>
            @elseif ($activationSprint)
                <div class="mb-4 rounded-xl border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
                    Sprint target otomatis: <span class="font-semibold">{{ $activationSprint->name }}</span>
                    ({{ optional($activationSprint->start_date)->toDateString() }} - {{ optional($activationSprint->end_date)->toDateString() }})
                </div>
            @else
                <div class="mb-4 rounded-xl border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-700">
                    Belum ada sprint aktif. Sistem akan membuat sprint minggu ini saat tombol aktivasi dijalankan.
                </div>
            @endif

            <form method="POST" action="{{ route('admin.projects.activate-sprint', $project) }}" class="space-y-4">
                @csrf
                @method('PATCH')

                <div class="max-h-72 space-y-2 overflow-auto rounded-xl border border-slate-200 p-3">
                    @forelse ($activationCandidates as $candidate)
                        <div class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                            <div class="flex items-start justify-between gap-3">
                                <span>
                                    <span class="font-medium">{{ $candidate->title }}</span>
                                    <span class="block text-xs text-slate-500">{{ $candidate->assignee?->name ?? 'Belum di-assign' }} | {{ $candidate->priority }} | {{ $candidate->status }}</span>
                                    <span class="block text-xs text-slate-400">Sprint saat ini: {{ $candidate->sprint?->name ?? 'Backlog' }}</span>
                                </span>
                                <input type="checkbox" name="backlog_ids[]" value="{{ $candidate->id }}" class="rounded border-slate-300" @checked(in_array($candidate->id, $activeBacklogIds, true))>
                            </div>

                            <div class="mt-2">
                                <select name="assignees[{{ $candidate->id }}]" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-xs" @disabled($interns->isEmpty())>
                                    <option value="">Pilih Assignee saat masuk sprint</option>
                                    @foreach ($interns as $intern)
                                        <option value="{{ $intern->id }}" @selected((int) $candidate->assignee_id === (int) $intern->id)>
                                            {{ $intern->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Belum ada backlog untuk diaktifkan.</p>
                    @endforelse
                </div>

                <button type="submit" @disabled($activationError || $interns->isEmpty()) class="w-full rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700 disabled:cursor-not-allowed disabled:bg-slate-400">Aktifkan Sprint</button>
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

                <input name="due_date" type="date" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">

                <select name="priority" required class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                    <option value="low">low</option>
                    <option value="medium" selected>medium</option>
                    <option value="high">high</option>
                    <option value="critical">critical</option>
                </select>

                <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-xs text-slate-600">
                    Backlog baru otomatis status <span class="font-semibold">todo</span> dan belum punya assignee.
                    Assignee ditentukan saat backlog dimasukkan ke sprint.
                </div>
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
            document.getElementById('edit-backlog-due-date').value = button.dataset.dueDate || '';
            document.getElementById('edit-backlog-priority').value = button.dataset.priority || 'medium';
            document.getElementById('edit-backlog-status').value = button.dataset.status || 'todo';
            document.getElementById('edit-backlog-modal').showModal();
        }
    </script>
@endsection
