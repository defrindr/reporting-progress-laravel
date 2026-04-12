@extends('layouts.app')

@section('content')
    <section class="space-y-6">
        <header>
            <h1 class="text-2xl font-semibold tracking-tight">CRUD Institutions</h1>
            <p class="text-sm text-slate-500">Kelola daftar universitas dan sekolah vokasi.</p>
        </header>

        <form method="POST" action="{{ route('admin.institutions.store') }}" class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:grid-cols-3">
            @csrf
            <input name="name" type="text" required placeholder="Nama institusi" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm sm:col-span-2">
            <select name="type" required class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                <option value="university">university</option>
                <option value="vocational">vocational</option>
            </select>
            <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700 sm:col-span-3">Tambah Institution</button>
        </form>

        <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-slate-600">
                    <tr>
                        <th class="px-4 py-3">ID</th>
                        <th class="px-4 py-3">Data Institution</th>
                        <th class="px-4 py-3">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($institutions as $institution)
                        <tr>
                            <td class="px-4 py-3">{{ $institution->id }}</td>
                            <td class="px-4 py-3">
                                <form method="POST" action="{{ route('admin.institutions.update', $institution) }}" class="flex flex-wrap items-center gap-2">
                                    @csrf
                                    @method('PUT')
                                    <input name="name" value="{{ $institution->name }}" required class="w-full min-w-60 rounded-lg border border-slate-300 px-2.5 py-2 text-sm">
                                    <select name="type" required class="rounded-lg border border-slate-300 px-2.5 py-2 text-sm">
                                        <option value="university" @selected($institution->type === 'university')>university</option>
                                        <option value="vocational" @selected($institution->type === 'vocational')>vocational</option>
                                    </select>
                                    <button type="submit" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold hover:bg-slate-50">Update</button>
                                </form>
                            </td>
                            <td class="px-4 py-3">
                                <form method="POST" action="{{ route('admin.institutions.destroy', $institution) }}" onsubmit="return confirm('Hapus institution ini?')" class="mt-2">
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
