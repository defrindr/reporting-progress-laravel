@extends('layouts.app')

@section('content')
    <section class="space-y-6">
        <header>
            <h1 class="text-2xl font-semibold tracking-tight">CRUD Periods</h1>
            <p class="text-sm text-slate-500">Setiap periode wajib terkait ke institution tipe university. Format holidays: YYYY-MM-DD dipisah koma.</p>
        </header>

        <form method="POST" action="{{ route('admin.periods.store') }}" class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:grid-cols-5">
            @csrf
            <select name="institution_id" required class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                <option value="">Pilih University</option>
                @foreach ($universities as $university)
                    <option value="{{ $university->id }}">{{ $university->name }}</option>
                @endforeach
            </select>
            <input name="name" type="text" required placeholder="Nama periode" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
            <input name="start_date" type="date" required class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
            <input name="end_date" type="date" required class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
            <input name="holidays" type="text" placeholder="2026-01-01,2026-01-02" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
            <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700 lg:col-span-5">Tambah Period</button>
        </form>

        <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-slate-600">
                    <tr>
                        <th class="px-4 py-3">ID</th>
                        <th class="px-4 py-3">Data Period</th>
                        <th class="px-4 py-3">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($periods as $period)
                        <tr>
                            <td class="px-4 py-3 align-top">{{ $period->id }}</td>
                            <td class="px-4 py-3">
                                <form method="POST" action="{{ route('admin.periods.update', $period) }}" class="grid gap-2 lg:grid-cols-5">
                                    @csrf
                                    @method('PUT')

                                    <select name="institution_id" required class="rounded-lg border border-slate-300 px-2.5 py-2 text-sm">
                                        @foreach ($universities as $university)
                                            <option value="{{ $university->id }}" @selected($period->institution_id === $university->id)>{{ $university->name }}</option>
                                        @endforeach
                                    </select>
                                    <input name="name" value="{{ $period->name }}" required class="rounded-lg border border-slate-300 px-2.5 py-2 text-sm">
                                    <input name="start_date" type="date" value="{{ optional($period->start_date)->toDateString() }}" required class="rounded-lg border border-slate-300 px-2.5 py-2 text-sm">
                                    <input name="end_date" type="date" value="{{ optional($period->end_date)->toDateString() }}" required class="rounded-lg border border-slate-300 px-2.5 py-2 text-sm">
                                    <input name="holidays" value="{{ implode(',', $period->holidays ?? []) }}" class="rounded-lg border border-slate-300 px-2.5 py-2 text-sm" placeholder="2026-01-01,2026-01-02">

                                    <div class="text-xs text-slate-500 lg:col-span-4">Periode: {{ optional($period->start_date)->toDateString() }} - {{ optional($period->end_date)->toDateString() }}</div>
                                    <button type="submit" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold hover:bg-slate-50 lg:col-span-1">Update</button>
                                </form>
                            </td>
                            <td class="px-4 py-3 align-top">
                                <form method="POST" action="{{ route('admin.periods.destroy', $period) }}" onsubmit="return confirm('Hapus period ini?')">
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
