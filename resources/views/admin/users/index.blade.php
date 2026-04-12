@extends('layouts.app')

@section('content')
    <section class="space-y-6">
        <header>
            <h1 class="text-2xl font-semibold tracking-tight">CRUD Users</h1>
        </header>

        <form method="POST" action="{{ route('admin.users.store') }}" class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:grid-cols-6">
            @csrf
            <input name="name" type="text" required placeholder="Nama" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-slate-900 lg:col-span-2">
            <input name="email" type="email" required placeholder="Email" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-slate-900 lg:col-span-2">
            <input name="password" type="password" required placeholder="Password min 8" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-slate-900 lg:col-span-2">

            <select name="institution_id" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-slate-900 lg:col-span-3">
                <option value="">Pilih Institution</option>
                @foreach ($institutions as $institution)
                    <option value="{{ $institution->id }}">{{ $institution->name }} ({{ $institution->type }})</option>
                @endforeach
            </select>

            <div class="rounded-xl border border-slate-300 px-3 py-2 lg:col-span-3">
                <p class="mb-1 text-xs font-medium text-slate-500">Roles</p>
                <div class="flex flex-wrap gap-3 text-sm">
                    @foreach ($roles as $role)
                        <label class="inline-flex items-center gap-1.5">
                            <input type="checkbox" name="roles[]" value="{{ $role->name }}" class="rounded border-slate-300">
                            {{ $role->name }}
                        </label>
                    @endforeach
                </div>
            </div>

            <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700 lg:col-span-6">Tambah User</button>
        </form>

        <div class="space-y-4">
            @foreach ($users as $user)
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="text-base font-semibold">{{ $user->name }}</h2>
                            <p class="text-sm text-slate-500">{{ $user->email }}</p>
                            <p class="text-xs text-slate-500">Roles: {{ $user->roles->pluck('name')->implode(', ') ?: '-' }}</p>
                        </div>
                        <form method="POST" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('Hapus user ini?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="rounded-lg bg-rose-600 px-3 py-1.5 text-sm text-white hover:bg-rose-500">Delete</button>
                        </form>
                    </div>

                    <form method="POST" action="{{ route('admin.users.update', $user) }}" class="grid gap-3 lg:grid-cols-6">
                        @csrf
                        @method('PUT')
                        <input name="name" value="{{ $user->name }}" required class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm lg:col-span-2">
                        <input name="email" type="email" value="{{ $user->email }}" required class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm lg:col-span-2">
                        <input name="password" type="password" placeholder="Kosongkan jika tidak ganti" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm lg:col-span-2">

                        <select name="institution_id" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm lg:col-span-3">
                            <option value="">Pilih Institution</option>
                            @foreach ($institutions as $institution)
                                <option value="{{ $institution->id }}" @selected($user->institution_id === $institution->id)>{{ $institution->name }} ({{ $institution->type }})</option>
                            @endforeach
                        </select>

                        <div class="rounded-xl border border-slate-300 px-3 py-2 lg:col-span-3">
                            <p class="mb-1 text-xs font-medium text-slate-500">Roles</p>
                            <div class="flex flex-wrap gap-3 text-sm">
                                @foreach ($roles as $role)
                                    <label class="inline-flex items-center gap-1.5">
                                        <input type="checkbox" name="roles[]" value="{{ $role->name }}" class="rounded border-slate-300" @checked($user->hasRole($role->name))>
                                        {{ $role->name }}
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <button type="submit" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold hover:bg-slate-50 lg:col-span-6">Update User</button>
                    </form>
                </article>
            @endforeach
        </div>
    </section>
@endsection
