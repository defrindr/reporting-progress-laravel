@extends('layouts.app')

@section('content')
    <section class="space-y-6">
        <header>
            <h1 class="text-2xl font-semibold tracking-tight">CRUD Roles</h1>
        </header>

        <form method="POST" action="{{ route('admin.roles.store') }}" class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:grid-cols-[1fr_auto]">
            @csrf
            <input name="name" type="text" required placeholder="Nama role (contoh: Supervisor)" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-slate-900">
            <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">Tambah Role</button>
        </form>

        <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-slate-600">
                    <tr>
                        <th class="px-4 py-3">ID</th>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($roles as $role)
                        <tr>
                            <td class="px-4 py-3">{{ $role->id }}</td>
                            <td class="px-4 py-3">
                                <form method="POST" action="{{ route('admin.roles.update', $role) }}" class="flex gap-2">
                                    @csrf
                                    @method('PUT')
                                    <input name="name" value="{{ $role->name }}" required class="w-full rounded-lg border border-slate-300 px-2.5 py-1.5">
                                    <button type="submit" class="rounded-lg border border-slate-300 px-3 py-1.5 hover:bg-slate-50">Update</button>
                                </form>
                            </td>
                            <td class="px-4 py-3">
                                <form method="POST" action="{{ route('admin.roles.destroy', $role) }}" onsubmit="return confirm('Hapus role ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="rounded-lg bg-rose-600 px-3 py-1.5 text-white hover:bg-rose-500">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endsection
