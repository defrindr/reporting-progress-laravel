@extends('layouts.app')

@php
    $groups = [
        'todo' => 'To Do',
        'doing' => 'Doing',
        'done' => 'Done',
    ];

    $taskItems = $tasks->getCollection();
    $isInternReadOnly = (bool) ($isInternReadOnly ?? false);
    $isWeekendRestriction = (bool) ($isWeekendRestriction ?? false);
    $nextWeekStartDate = $nextWeekStartDate ?? null;
    $viewMode = $viewMode ?? 'kanban';
    $filters = $filters ?? [];
@endphp

@section('content')
    <section class="space-y-6">
        <header>
            <h1 class="text-2xl font-semibold tracking-tight">Project Board</h1>
            <p class="mt-1 text-sm text-slate-500">Board kanban ditampilkan per sprint dan mendukung drag-and-drop status
                task.</p>
        </header>

        @if (!$isManager && $isInternReadOnly)
            <article class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
                <p class="font-semibold">Mode Read-Only</p>
                <p class="mt-1">{{ $readOnlyReason ?? 'Tidak ada periode aktif untuk tanggal ini.' }}</p>
            </article>
        @endif

        @if (!$isManager && !$isInternReadOnly && $isWeekendRestriction)
            <article class="rounded-xl border border-sky-300 bg-sky-50 p-4 text-sm text-sky-900">
                <p class="font-semibold">Mode Weekend</p>
                <p class="mt-1">Sabtu/Minggu tidak bisa ubah status kanban. Kamu tetap bisa tambah backlog untuk minggu depan (due date mulai {{ $nextWeekStartDate ?? '-' }}).</p>
            </article>
        @endif

        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <form method="GET" action="{{ route('projects.board') }}" class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                <input type="hidden" name="view_mode" value="{{ $viewMode }}">

                <select name="sprint_id" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                    <option value="">Pilih Sprint</option>
                    @foreach ($sprints as $sprint)
                        <option value="{{ $sprint->id }}" @selected($selectedSprint?->id === $sprint->id)>
                            {{ $sprint->name }} ({{ $sprint->institution?->name ?? '-' }})
                        </option>
                    @endforeach
                </select>

                <select name="project_spec_id" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                    <option value="">Semua Project</option>
                    @foreach ($projectFilters as $projectOption)
                        <option value="{{ $projectOption->id }}" @selected((int) ($filters['project_spec_id'] ?? 0) === (int) $projectOption->id)>
                            {{ $projectOption->title }}
                        </option>
                    @endforeach
                </select>

                @if ($isManager)
                    <select name="assignee_id" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                        <option value="">Semua Intern</option>
                        @foreach ($assigneeFilters as $assigneeOption)
                            <option value="{{ $assigneeOption->id }}" @selected((int) ($filters['assignee_id'] ?? 0) === (int) $assigneeOption->id)>
                                {{ $assigneeOption->name }}
                            </option>
                        @endforeach
                    </select>
                @else
                    <select name="status" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                        <option value="">Semua Status</option>
                        <option value="todo" @selected(($filters['status'] ?? null) === 'todo')>todo</option>
                        <option value="doing" @selected(($filters['status'] ?? null) === 'doing')>doing</option>
                        <option value="done" @selected(($filters['status'] ?? null) === 'done')>done</option>
                    </select>
                @endif

                @if ($isManager)
                    <select name="status" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                        <option value="">Semua Status</option>
                        <option value="todo" @selected(($filters['status'] ?? null) === 'todo')>todo</option>
                        <option value="doing" @selected(($filters['status'] ?? null) === 'doing')>doing</option>
                        <option value="done" @selected(($filters['status'] ?? null) === 'done')>done</option>
                    </select>
                @endif

                <label class="inline-flex items-center gap-2 rounded-xl border border-slate-300 px-3 py-2.5 text-sm text-slate-700">
                    <input type="checkbox" name="overdue" value="1" class="h-4 w-4 rounded border-slate-300 text-slate-900"
                        @checked(!empty($filters['overdue']))>
                    Overdue saja
                </label>

                <input name="keyword" type="text" value="{{ $filters['keyword'] ?? '' }}"
                    placeholder="Cari task/deskripsi/project..."
                    class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm md:col-span-2 xl:col-span-1">

                <div class="flex gap-2 md:col-span-2 xl:col-span-4">
                    <button type="submit"
                        class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm hover:bg-slate-50">Terapkan
                        Filter</button>
                    <a href="{{ route('projects.board') }}"
                        class="rounded-xl border border-slate-300 px-4 py-2.5 text-center text-sm hover:bg-slate-50">Reset</a>
                </div>
            </form>

            @if ($selectedSprint)
                <p class="mt-3 text-xs text-slate-500">Sprint aktif: {{ $selectedSprint->name }}
                    ({{ $selectedSprint->start_date->toDateString() }} - {{ $selectedSprint->end_date->toDateString() }})
                </p>

                <div class="mt-3 flex flex-wrap gap-2">
                    @if ($previousSprintId)
                        <a href="{{ route('projects.board', array_merge(request()->except('page'), ['sprint_id' => $previousSprintId])) }}"
                            class="rounded-xl border border-slate-300 px-4 py-2 text-xs hover:bg-slate-50">Week
                            Sebelumnya</a>
                    @endif
                    @if ($nextSprintId)
                        <a href="{{ route('projects.board', array_merge(request()->except('page'), ['sprint_id' => $nextSprintId])) }}"
                            class="rounded-xl border border-slate-300 px-4 py-2 text-xs hover:bg-slate-50">Week
                            Berikutnya</a>
                    @endif
                </div>
            @else
                <p class="mt-3 text-xs text-rose-600">Belum ada sprint tersedia untuk akun ini.</p>
            @endif
        </article>

        @if ($isManager)
            <nav class="inline-flex rounded-xl border border-slate-200 bg-white p-1 shadow-sm">
                <a href="{{ route('projects.board', array_merge(request()->except('page'), ['view_mode' => 'kanban'])) }}"
                    class="rounded-lg px-4 py-2 text-sm {{ $viewMode === 'kanban' ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100' }}">Kanban</a>
                <a href="{{ route('projects.board', array_merge(request()->except('page'), ['view_mode' => 'table'])) }}"
                    class="rounded-lg px-4 py-2 text-sm {{ $viewMode === 'table' ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100' }}">Table</a>
            </nav>
        @endif

        @if (!$isManager)
            <details
                class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm [&_summary::-webkit-details-marker]:hidden">
                <summary
                    class="flex cursor-pointer items-center justify-between gap-1.5 text-lg font-semibold text-slate-900 list-none">
                    Tambah Task Pribadi Intern

                    <svg class="h-5 w-5 shrink-0 transition duration-300 group-open:-rotate-180"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </summary>

                <div class="mt-4 border-t border-slate-100 pt-4">
                    <p class="text-sm text-slate-500">Task harus memilih project dan due date. Sprint akan pakai sprint
                        aktif atau otomatis dari due date.</p>
                    @if ($isInternReadOnly)
                        <p class="mt-2 text-xs font-medium text-amber-700">Form dinonaktifkan karena akun intern berada di mode read-only.</p>
                    @elseif ($isWeekendRestriction)
                        <p class="mt-2 text-xs font-medium text-sky-700">Weekend: hanya backlog untuk minggu depan yang diperbolehkan.</p>
                    @endif

                    <form method="POST" action="{{ route('projects.tasks.store') }}"
                        class="mt-4 grid gap-3 lg:grid-cols-2">
                        @csrf
                        <input type="hidden" name="sprint_id" value="{{ $selectedSprint?->id }}">

                        <select name="project_spec_id" required
                            @disabled($isInternReadOnly)
                            class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm disabled:cursor-not-allowed disabled:bg-slate-100">
                            <option value="">Pilih Project</option>
                            @foreach ($availableProjects as $projectOption)
                                <option value="{{ $projectOption->id }}">{{ $projectOption->title }}</option>
                            @endforeach
                        </select>

                        <input name="due_date" type="date" required
                            min="{{ $isWeekendRestriction ? $nextWeekStartDate : '' }}"
                            @disabled($isInternReadOnly)
                            class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm disabled:cursor-not-allowed disabled:bg-slate-100">

                        <input name="title" type="text" required placeholder="Nama task"
                            @disabled($isInternReadOnly)
                            class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm lg:col-span-2 disabled:cursor-not-allowed disabled:bg-slate-100">

                        <textarea name="description" rows="3" placeholder="Detail task tambahan (opsional)"
                            @disabled($isInternReadOnly)
                            class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm lg:col-span-2 disabled:cursor-not-allowed disabled:bg-slate-100"></textarea>

                        <select name="priority" @disabled($isInternReadOnly)
                            class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm disabled:cursor-not-allowed disabled:bg-slate-100">
                            <option value="low">low</option>
                            <option value="medium" selected>medium</option>
                            <option value="high">high</option>
                            <option value="critical">critical</option>
                        </select>

                        <button type="submit"
                            @disabled($isInternReadOnly)
                            class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">Simpan
                            Task</button>
                    </form>
                </div>
            </details>
        @endif

        @if (!$isManager || $viewMode === 'kanban')
            <div class="grid gap-4 lg:grid-cols-3" data-kanban-board>
            @foreach ($groups as $status => $label)
                <article class="kanban-drop-zone rounded-2xl border border-slate-200 bg-slate-50/70 p-4"
                    data-drop-status="{{ $status }}">
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="text-sm font-semibold text-slate-800">{{ $label }}</h2>
                        <span
                            class="rounded-full bg-white px-2.5 py-1 text-xs font-medium text-slate-700">{{ $taskItems->where('status', $status)->count() }}</span>
                    </div>

                    <div class="space-y-3">
                        @forelse ($taskItems->where('status', $status) as $project)
                            @php($canDrag = !$isManager && !$isInternReadOnly && !$isWeekendRestriction && auth()->id() === $project->assignee_id)
                            <div class="kanban-card rounded-xl border border-slate-200 bg-white p-3 shadow-sm {{ $canDrag ? 'cursor-grab' : '' }}"
                                draggable="{{ $canDrag ? 'true' : 'false' }}" data-project-id="{{ $project->id }}"
                                data-current-status="{{ $project->status }}">
                                <h3 class="text-sm font-semibold text-slate-900">{{ $project->title }}</h3>
                                <p class="mt-1 text-xs text-slate-500">Intern: {{ $project->assignee?->name ?? '-' }}</p>
                                <p class="mt-1 text-xs text-slate-500">Project: {{ $project->spec?->title ?? '-' }}</p>
                                <p class="mt-1 text-xs text-slate-500">Priority: {{ $project->priority }} | Due:
                                    {{ optional($project->due_date)->toDateString() ?? '-' }}</p>
                                <p class="mt-2 text-sm text-slate-700">{{ $project->description ?: 'Tanpa deskripsi' }}</p>

                                @if ($canDrag)
                                    <p class="mt-2 text-[11px] font-medium uppercase tracking-wide text-slate-500">Drag card
                                        ini ke kolom lain untuk ubah status</p>
                                @endif

                                @if (!$isManager && auth()->id() === $project->assignee_id && !$isInternReadOnly && !$isWeekendRestriction)
                                    <form method="POST" action="{{ route('projects.status', $project) }}"
                                        class="mt-3 flex items-center gap-2 text-xs">
                                        @csrf
                                        @method('PATCH')
                                        <select name="status" class="rounded-lg border border-slate-300 px-2 py-1.5">
                                            <option value="todo" @selected($project->status === 'todo')>todo</option>
                                            <option value="doing" @selected($project->status === 'doing')>doing</option>
                                            <option value="done" @selected($project->status === 'done')>done</option>
                                        </select>
                                        <button type="submit"
                                            class="rounded-lg border border-slate-300 px-3 py-1.5 font-medium hover:bg-slate-50">Set
                                            Status</button>
                                    </form>
                                @elseif (!$isManager && auth()->id() === $project->assignee_id && $isWeekendRestriction)
                                    <p class="mt-3 text-xs font-medium text-sky-700">Status dikunci saat weekend. Tambah backlog untuk minggu depan.</p>
                                @elseif (!$isManager && auth()->id() === $project->assignee_id && $isInternReadOnly)
                                    <p class="mt-3 text-xs font-medium text-amber-700">Status dikunci karena akun intern read-only.</p>
                                @endif

                                @if ($isManager && $project->status === 'todo')
                                    <form method="POST" action="{{ route('projects.reassign', $project) }}"
                                        class="mt-3 flex items-center gap-2 text-xs">
                                        @csrf
                                        @method('PATCH')
                                        <select name="assignee_id" class="rounded-lg border border-slate-300 px-2 py-1.5" required>
                                            @foreach ($assigneeFilters as $assigneeOption)
                                                <option value="{{ $assigneeOption->id }}" @selected((int) $project->assignee_id === (int) $assigneeOption->id)>
                                                    {{ $assigneeOption->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <button type="submit"
                                            class="rounded-lg border border-slate-300 px-3 py-1.5 font-medium hover:bg-slate-50">Ubah
                                            Assignee</button>
                                    </form>
                                @endif

                                <div class="mt-3 rounded-lg border border-slate-100 bg-slate-50 p-2.5">
                                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">History</p>
                                    <ul class="mt-2 space-y-1 text-xs text-slate-600">
                                        @forelse (($activityByProject[$project->id] ?? collect())->take(4) as $activity)
                                            <li class="rounded bg-white px-2 py-1.5">
                                                {{ $activity->description }}
                                                <span class="text-slate-400">({{ $activity->created_at }})</span>
                                            </li>
                                        @empty
                                            <li class="text-slate-500">Belum ada history.</li>
                                        @endforelse
                                    </ul>
                                </div>

                                <div class="mt-3 rounded-lg border border-slate-100 bg-slate-50 p-2.5">
                                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Comments</p>
                                    <ul class="mt-2 space-y-1 text-xs text-slate-700">
                                        @forelse ($project->comments->take(5) as $comment)
                                            <li class="rounded bg-white px-2 py-1.5"><span
                                                    class="font-medium">{{ $comment->user?->name }}:</span>
                                                {{ $comment->body }}</li>
                                        @empty
                                            <li class="text-slate-500">Belum ada komentar.</li>
                                        @endforelse
                                    </ul>

                                    <form method="POST" action="{{ route('projects.comment', $project) }}"
                                        class="mt-2 space-y-2">
                                        @csrf
                                        <textarea name="body" rows="2" required placeholder="Tambah komentar..."
                                            class="w-full rounded-lg border border-slate-300 px-2.5 py-2 text-xs"></textarea>
                                        <button type="submit"
                                            class="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-700">Post
                                            Comment</button>
                                    </form>
                                </div>
                            </div>
                        @empty
                            <p
                                class="rounded-xl border border-dashed border-slate-300 bg-white px-3 py-6 text-center text-xs text-slate-500">
                                Tidak ada project.</p>
                        @endforelse
                    </div>
                </article>
            @endforeach
            </div>
        @endif

        <div>
            {{ $tasks->links() }}
        </div>

        @if ($isManager && $viewMode === 'table')
            <article class="overflow-x-auto rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="mb-3 text-base font-semibold">Tabel Monitoring Task</h2>
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-slate-600">
                        <tr>
                            <th class="px-3 py-2">Tanggal</th>
                            <th class="px-3 py-2">Project</th>
                            <th class="px-3 py-2">Intern</th>
                            <th class="px-3 py-2">Task</th>
                            <th class="px-3 py-2">Due</th>
                            <th class="px-3 py-2">Priority</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2">Creator</th>
                            <th class="px-3 py-2">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($tasks as $task)
                            <tr>
                                <td class="px-3 py-2">{{ optional($task->created_at)->toDateString() }}</td>
                                <td class="px-3 py-2">{{ $task->spec?->title ?? '-' }}</td>
                                <td class="px-3 py-2">{{ $task->assignee?->name ?? '-' }}</td>
                                <td class="px-3 py-2">{{ $task->title }}</td>
                                <td class="px-3 py-2">{{ optional($task->due_date)->toDateString() ?? '-' }}</td>
                                <td class="px-3 py-2">{{ $task->priority }}</td>
                                <td class="px-3 py-2">{{ $task->status }}</td>
                                <td class="px-3 py-2">{{ $task->creator?->name ?? '-' }}</td>
                                <td class="px-3 py-2">
                                    @if ($task->status === 'todo')
                                        <form method="POST" action="{{ route('projects.reassign', $task) }}" class="flex items-center gap-2">
                                            @csrf
                                            @method('PATCH')
                                            <select name="assignee_id" class="rounded-lg border border-slate-300 px-2 py-1.5 text-xs" required>
                                                @foreach ($assigneeFilters as $assigneeOption)
                                                    <option value="{{ $assigneeOption->id }}" @selected((int) $task->assignee_id === (int) $assigneeOption->id)>
                                                        {{ $assigneeOption->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <button type="submit"
                                                class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs font-medium hover:bg-slate-50">Simpan</button>
                                        </form>
                                    @else
                                        <span class="text-xs text-slate-400">Hanya todo</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-3 py-6 text-center text-sm text-slate-500">Tidak ada task
                                    pada sprint ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </article>
        @endif
    </section>

    <script>
        (function() {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
            const board = document.querySelector('[data-kanban-board]');

            if (!csrfToken || !board) {
                return;
            }

            let activeCard = null;

            document.querySelectorAll('.kanban-card[draggable="true"]').forEach((card) => {
                card.addEventListener('dragstart', () => {
                    activeCard = card;
                    card.classList.add('opacity-60');
                });

                card.addEventListener('dragend', () => {
                    card.classList.remove('opacity-60');
                    activeCard = null;
                });
            });

            document.querySelectorAll('.kanban-drop-zone').forEach((dropZone) => {
                dropZone.addEventListener('dragover', (event) => {
                    if (!activeCard) {
                        return;
                    }

                    event.preventDefault();
                    dropZone.classList.add('ring-2', 'ring-slate-300');
                });

                dropZone.addEventListener('dragleave', () => {
                    dropZone.classList.remove('ring-2', 'ring-slate-300');
                });

                dropZone.addEventListener('drop', async (event) => {
                    event.preventDefault();
                    dropZone.classList.remove('ring-2', 'ring-slate-300');

                    if (!activeCard) {
                        return;
                    }

                    const nextStatus = dropZone.dataset.dropStatus;
                    const currentStatus = activeCard.dataset.currentStatus;

                    if (!nextStatus || currentStatus === nextStatus) {
                        return;
                    }

                    const projectId = activeCard.dataset.projectId;

                    try {
                        const response = await fetch(
                        `{{ url('/projects') }}/${projectId}/status`, {
                            method: 'PATCH',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                            },
                            body: JSON.stringify({
                                status: nextStatus
                            }),
                        });

                        if (!response.ok) {
                            const payload = await response.json().catch(() => ({}));
                            alert(payload.message || 'Gagal mengubah status task.');
                            return;
                        }

                        window.location.reload();
                    } catch (error) {
                        alert('Terjadi error saat drag-and-drop status task.');
                    }
                });
            });
        })();
    </script>
@endsection
