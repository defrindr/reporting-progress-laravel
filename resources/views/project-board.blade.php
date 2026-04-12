@extends('layouts.app')

@php
    $groups = [
        'todo' => 'To Do',
        'doing' => 'Doing',
        'done' => 'Done',
    ];
@endphp

@section('content')
    <section class="space-y-6">
        <header>
            <h1 class="text-2xl font-semibold tracking-tight">Project Board</h1>
            <p class="mt-1 text-sm text-slate-500">Intern membuat task dari project spec yang di-assign. Status task bisa maju atau mundur.</p>
        </header>

        @if ($isManager)
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold">Monitoring Task Harian</h2>
                        <p class="text-sm text-slate-500">Filter task berdasarkan tanggal pembuatan untuk monitoring intern.</p>
                    </div>

                    <form method="GET" action="{{ route('projects.board') }}" class="flex items-center gap-2 text-sm">
                        <input type="date" name="task_date" value="{{ $taskDate }}" class="rounded-lg border border-slate-300 px-3 py-2">
                        <button type="submit" class="rounded-lg border border-slate-300 px-3 py-2 hover:bg-slate-50">Filter</button>
                        @if ($taskDate)
                            <a href="{{ route('projects.board') }}" class="rounded-lg border border-slate-300 px-3 py-2 hover:bg-slate-50">Reset</a>
                        @endif
                    </form>
                </div>
            </article>
        @else
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold">Buat Task dari Project Spec</h2>
                <p class="mt-1 text-sm text-slate-500">Pilih salah satu project spec yang di-assign ke akun intern kamu.</p>

                <form method="POST" action="{{ route('projects.tasks.store') }}" class="mt-4 grid gap-3 lg:grid-cols-4">
                    @csrf
                    <select name="project_spec_id" required class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm lg:col-span-2">
                        <option value="">Pilih Project Spec</option>
                        @foreach ($assignedSpecs as $spec)
                            <option value="{{ $spec->id }}">{{ $spec->title }}</option>
                        @endforeach
                    </select>
                    <input name="title" type="text" required placeholder="Judul task" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm lg:col-span-2">
                    <textarea name="description" rows="3" placeholder="Deskripsi task" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm lg:col-span-4"></textarea>
                    <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700 lg:col-span-4">Create Task</button>
                </form>
            </article>
        @endif

        <div class="grid gap-4 lg:grid-cols-3">
            @foreach ($groups as $status => $label)
                <article class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="text-sm font-semibold text-slate-800">{{ $label }}</h2>
                        <span class="rounded-full bg-white px-2.5 py-1 text-xs font-medium text-slate-700">{{ $tasks->where('status', $status)->count() }}</span>
                    </div>

                    <div class="space-y-3">
                        @forelse ($tasks->where('status', $status) as $project)
                            <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
                                <h3 class="text-sm font-semibold text-slate-900">{{ $project->title }}</h3>
                                <p class="mt-1 text-xs text-slate-500">Intern: {{ $project->assignee?->name ?? '-' }}</p>
                                <p class="mt-1 text-xs text-slate-500">Spec: {{ $project->spec?->title ?? '-' }}</p>
                                <p class="mt-2 text-sm text-slate-700">{{ $project->description ?: 'Tanpa deskripsi' }}</p>

                                @if (! $isManager && auth()->id() === $project->assignee_id)
                                    <form method="POST" action="{{ route('projects.status', $project) }}" class="mt-3 flex items-center gap-2 text-xs">
                                        @csrf
                                        @method('PATCH')
                                        <select name="status" class="rounded-lg border border-slate-300 px-2 py-1.5">
                                            <option value="todo" @selected($project->status === 'todo')>todo</option>
                                            <option value="doing" @selected($project->status === 'doing')>doing</option>
                                            <option value="done" @selected($project->status === 'done')>done</option>
                                        </select>
                                        <button type="submit" class="rounded-lg border border-slate-300 px-3 py-1.5 font-medium hover:bg-slate-50">Set Status</button>
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
                                            <li class="rounded bg-white px-2 py-1.5"><span class="font-medium">{{ $comment->user?->name }}:</span> {{ $comment->body }}</li>
                                        @empty
                                            <li class="text-slate-500">Belum ada komentar.</li>
                                        @endforelse
                                    </ul>

                                    <form method="POST" action="{{ route('projects.comment', $project) }}" class="mt-2 space-y-2">
                                        @csrf
                                        <textarea name="body" rows="2" required placeholder="Tambah komentar..." class="w-full rounded-lg border border-slate-300 px-2.5 py-2 text-xs"></textarea>
                                        <button type="submit" class="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-700">Post Comment</button>
                                    </form>
                                </div>
                            </div>
                        @empty
                            <p class="rounded-xl border border-dashed border-slate-300 bg-white px-3 py-6 text-center text-xs text-slate-500">Tidak ada project.</p>
                        @endforelse
                    </div>
                </article>
            @endforeach
        </div>

        @if ($isManager)
            <article class="overflow-x-auto rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="mb-3 text-base font-semibold">Tabel Monitoring Task</h2>
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
                                <td class="px-3 py-2">{{ $task->title }}</td>
                                <td class="px-3 py-2">{{ $task->spec?->title ?? '-' }}</td>
                                <td class="px-3 py-2">{{ $task->status }}</td>
                                <td class="px-3 py-2">{{ $task->creator?->name ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-3 py-6 text-center text-sm text-slate-500">Tidak ada task pada filter ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </article>
        @endif
    </section>
@endsection
