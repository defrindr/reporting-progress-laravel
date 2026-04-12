@extends('layouts.app')

@section('content')
    <section class="space-y-6">
        <header>
            <h1 class="text-2xl font-semibold tracking-tight">Project Specs & Monitoring</h1>
            <p class="text-sm text-slate-500">Admin membuat project spec dan assign ke banyak intern. Task harian dipantau dari tabel monitoring.</p>
        </header>

        <form method="POST" action="{{ route('admin.projects.store') }}" class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:grid-cols-6">
            @csrf
            <input name="title" type="text" required placeholder="Judul project spec" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm lg:col-span-2">
            <textarea name="specification" rows="3" required placeholder="Spesifikasi detail project" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm lg:col-span-4"></textarea>

            <div class="rounded-xl border border-slate-300 px-3 py-2 lg:col-span-6">
                <p class="mb-1 text-xs font-medium text-slate-500">Assign ke Intern</p>
                <div class="flex flex-wrap gap-3 text-sm">
                    @foreach ($interns as $intern)
                        <label class="inline-flex items-center gap-1.5">
                            <input type="checkbox" name="intern_ids[]" value="{{ $intern->id }}" class="rounded border-slate-300">
                            {{ $intern->name }}
                        </label>
                    @endforeach
                </div>
            </div>

            <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700 lg:col-span-6">Tambah Project Spec</button>
        </form>

        <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-slate-600">
                    <tr>
                        <th class="px-4 py-3">ID</th>
                        <th class="px-4 py-3">Project Spec</th>
                        <th class="px-4 py-3">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($specs as $spec)
                        @php($assignedIds = $spec->assignedInterns->pluck('id')->all())
                        <tr>
                            <td class="px-4 py-3 align-top">{{ $spec->id }}</td>
                            <td class="px-4 py-3">
                                <form method="POST" action="{{ route('admin.projects.update', $spec) }}" class="grid gap-2">
                                    @csrf
                                    @method('PUT')

                                    <input name="title" value="{{ $spec->title }}" required class="rounded-lg border border-slate-300 px-2.5 py-2 text-sm">
                                    <textarea name="specification" rows="3" required class="rounded-lg border border-slate-300 px-2.5 py-2 text-sm">{{ $spec->specification }}</textarea>
                                    <select name="intern_ids[]" multiple required class="rounded-lg border border-slate-300 px-2.5 py-2 text-sm" size="4">
                                        @foreach ($interns as $intern)
                                            <option value="{{ $intern->id }}" @selected(in_array($intern->id, $assignedIds, true))>{{ $intern->name }}</option>
                                        @endforeach
                                    </select>

                                    <p class="text-xs text-slate-500">Assigned: {{ $spec->assignedInterns->pluck('name')->implode(', ') ?: '-' }}</p>
                                    <button type="submit" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold hover:bg-slate-50">Update Spec</button>
                                </form>
                            </td>
                            <td class="px-4 py-3 align-top">
                                <form method="POST" action="{{ route('admin.projects.destroy', $spec) }}" onsubmit="return confirm('Hapus project spec ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="rounded-lg bg-rose-600 px-3 py-2 text-xs font-semibold text-white hover:bg-rose-500">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
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
        </section>
    </section>
@endsection
