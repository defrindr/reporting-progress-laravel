@extends('layouts.app')

@section('content')
    <section class="space-y-6">
        <header>
            <h1 class="text-2xl font-semibold tracking-tight">CRUD Periods</h1>
            <p class="text-sm text-slate-500">Format holidays: YYYY-MM-DD, dipisah koma.</p>
        </header>

        <form method="POST" action="{{ route('admin.periods.store') }}" class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:grid-cols-4">
            @csrf
            <input name="name" type="text" required placeholder="Nama periode" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
            <input name="start_date" type="date" required class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
            <input name="end_date" type="date" required class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
            <input name="holidays" type="text" placeholder="2026-01-01,2026-01-02" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
            <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700 lg:col-span-4">Tambah Period</button>
        </form>

        <div class="space-y-3">
            @foreach ($periods as $period)
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <form method="POST" action="{{ route('admin.periods.update', $period) }}" class="grid gap-3 lg:grid-cols-4">
                        @csrf
                        @method('PUT')
                        <input name="name" value="{{ $period->name }}" required class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                        <input name="start_date" type="date" value="{{ optional($period->start_date)->toDateString() }}" required class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                        <input name="end_date" type="date" value="{{ optional($period->end_date)->toDateString() }}" required class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                        <input name="holidays" value="{{ implode(',', $period->holidays ?? []) }}" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                        <button type="submit" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm hover:bg-slate-50 lg:col-span-3">Update</button>
                    </form>
                    <form method="POST" action="{{ route('admin.periods.destroy', $period) }}" onsubmit="return confirm('Hapus period ini?')" class="mt-2 text-right">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="rounded-lg bg-rose-600 px-3 py-1.5 text-xs text-white hover:bg-rose-500">Delete</button>
                    </form>
                </article>
            @endforeach
        </div>
    </section>
@endsection
