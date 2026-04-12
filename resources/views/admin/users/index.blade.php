@extends('layouts.app')

@section('content')
    <section class="space-y-6">
        <header>
            <h1 class="text-2xl font-semibold tracking-tight">CRUD Users</h1>
            <p class="text-sm text-slate-500">Manajemen akun Admin, Supervisor, dan Intern.</p>
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

        <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-slate-600">
                    <tr>
                        <th class="px-4 py-3">ID</th>
                        <th class="px-4 py-3">Data User</th>
                        <th class="px-4 py-3">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($users as $user)
                        <tr>
                            <td class="px-4 py-3 align-top">{{ $user->id }}</td>
                            <td class="px-4 py-3">
                                <form method="POST" action="{{ route('admin.users.update', $user) }}" class="grid gap-2 lg:grid-cols-6">
                                    @csrf
                                    @method('PUT')

                                    <input name="name" value="{{ $user->name }}" required class="rounded-lg border border-slate-300 px-2.5 py-2 text-sm lg:col-span-2">
                                    <input name="email" type="email" value="{{ $user->email }}" required class="rounded-lg border border-slate-300 px-2.5 py-2 text-sm lg:col-span-2">
                                    <input name="password" type="password" placeholder="Password baru (opsional)" class="rounded-lg border border-slate-300 px-2.5 py-2 text-sm lg:col-span-2">

                                    <select name="institution_id" class="rounded-lg border border-slate-300 px-2.5 py-2 text-sm lg:col-span-3">
                                        <option value="">Pilih Institution</option>
                                        @foreach ($institutions as $institution)
                                            <option value="{{ $institution->id }}" @selected($user->institution_id === $institution->id)>{{ $institution->name }} ({{ $institution->type }})</option>
                                        @endforeach
                                    </select>

                                    <select name="roles[]" multiple required class="rounded-lg border border-slate-300 px-2.5 py-2 text-sm lg:col-span-2" size="3">
                                        @foreach ($roles as $role)
                                            <option value="{{ $role->name }}" @selected($user->hasRole($role->name))>{{ $role->name }}</option>
                                        @endforeach
                                    </select>

                                    <div class="text-xs text-slate-500 lg:col-span-1">Roles aktif: {{ $user->roles->pluck('name')->implode(', ') ?: '-' }}</div>

                                    <button type="submit" class="rounded-lg border border-slate-300 px-3 py-2 text-sm hover:bg-slate-50 lg:col-span-6">Update User</button>
                                </form>
                            </td>
                            <td class="px-4 py-3 align-top">
                                <form method="POST" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('Hapus user ini?')">
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
    </section>
@endsection
