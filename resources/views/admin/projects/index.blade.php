@extends('layouts.app')

@section('content')
    <section class="space-y-6">
        <header>
            <h1 class="text-2xl font-semibold tracking-tight">CRUD Projects (Assignment)</h1>
        </header>

        <form method="POST" action="{{ route('admin.projects.store') }}" class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:grid-cols-4">
            @csrf
            <input name="title" type="text" required placeholder="Judul project" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm lg:col-span-2">
            <select name="assignee_id" required class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                <option value="">Pilih assignee</option>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                @endforeach
            </select>
            <select name="status" required class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                <option value="todo">todo</option>
                <option value="doing">doing</option>
                <option value="done">done</option>
            </select>
            <textarea name="description" rows="3" placeholder="Deskripsi project" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm lg:col-span-4"></textarea>
            <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700 lg:col-span-4">Tambah Project</button>
        </form>

        <div class="space-y-3">
            @foreach ($projects as $project)
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <form method="POST" action="{{ route('admin.projects.update', $project) }}" class="grid gap-3 lg:grid-cols-4">
                        @csrf
                        @method('PUT')
                        <input name="title" value="{{ $project->title }}" required class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm lg:col-span-2">
                        <select name="assignee_id" required class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                            @foreach ($users as $user)
                                <option value="{{ $user->id }}" @selected($project->assignee_id === $user->id)>{{ $user->name }}</option>
                            @endforeach
                        </select>
                        <select name="status" required class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                            <option value="todo" @selected($project->status === 'todo')>todo</option>
                            <option value="doing" @selected($project->status === 'doing')>doing</option>
                            <option value="done" @selected($project->status === 'done')>done</option>
                        </select>
                        <textarea name="description" rows="3" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm lg:col-span-4">{{ $project->description }}</textarea>
                        <button type="submit" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm hover:bg-slate-50 lg:col-span-3">Update</button>
                    </form>
                    <form method="POST" action="{{ route('admin.projects.destroy', $project) }}" onsubmit="return confirm('Hapus project ini?')" class="mt-2 text-right">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="rounded-lg bg-rose-600 px-3 py-1.5 text-xs text-white hover:bg-rose-500">Delete</button>
                    </form>
                </article>
            @endforeach
        </div>
    </section>
@endsection
