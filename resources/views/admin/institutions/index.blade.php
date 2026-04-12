@extends('layouts.app')

@section('content')
    <section class="space-y-6">
        <header>
            <h1 class="text-2xl font-semibold tracking-tight">CRUD Institutions</h1>
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

        <div class="space-y-3">
            @foreach ($institutions as $institution)
                <form method="POST" action="{{ route('admin.institutions.update', $institution) }}" class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-[1fr_180px_auto_auto]">
                    @csrf
                    @method('PUT')
                    <input name="name" value="{{ $institution->name }}" required class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                    <select name="type" required class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                        <option value="university" @selected($institution->type === 'university')>university</option>
                        <option value="vocational" @selected($institution->type === 'vocational')>vocational</option>
                    </select>
                    <button type="submit" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm hover:bg-slate-50">Update</button>
                </form>
                <form method="POST" action="{{ route('admin.institutions.destroy', $institution) }}" onsubmit="return confirm('Hapus institution ini?')" class="-mt-2 text-right">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="rounded-lg bg-rose-600 px-3 py-1.5 text-xs text-white hover:bg-rose-500">Delete</button>
                </form>
            @endforeach
        </div>
    </section>
@endsection
