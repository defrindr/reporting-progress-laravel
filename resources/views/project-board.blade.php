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
            <p class="mt-1 text-sm text-slate-500">Track assignment project, komentar, dan history perubahan status.</p>
        </header>

        <div class="grid gap-4 lg:grid-cols-3">
            @foreach ($groups as $status => $label)
                <article class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="text-sm font-semibold text-slate-800">{{ $label }}</h2>
                        <span class="rounded-full bg-white px-2.5 py-1 text-xs font-medium text-slate-700">{{ $projects->where('status', $status)->count() }}</span>
                    </div>

                    <div class="space-y-3">
                        @forelse ($projects->where('status', $status) as $project)
                            <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
                                <h3 class="text-sm font-semibold text-slate-900">{{ $project->title }}</h3>
                                <p class="mt-1 text-xs text-slate-500">Assignee: {{ $project->assignee?->name ?? '-' }}</p>
                                <p class="mt-2 text-sm text-slate-700">{{ $project->description ?: 'Tanpa deskripsi' }}</p>

                                @if ($project->status !== 'done')
                                    <form method="POST" action="{{ route('projects.advance', $project) }}" class="mt-3">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium hover:bg-slate-50">Move Next</button>
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
    </section>
@endsection
