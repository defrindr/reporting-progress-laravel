@extends('layouts.app')

@php
    $selectedPeriod = $selectedPeriod ?? null;
    $rows = collect($rows ?? []);
    $detailRows = collect($detailRows ?? []);

    $detailInternName = collect($internOptions ?? [])->firstWhere('id', $detailInternId ?? 0)?->name;
@endphp

@section('content')
    <section class="space-y-6">
        <header class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Evaluation Lab (Eksperimen)</h1>
                <p class="mt-1 text-sm text-slate-500">
                    Simulasi scoring akhir magang berbasis logbook + task. Aturan inti: hari kerja non-libur tanpa logbook = nilai 0.
                </p>
            </div>

            <span class="rounded-full border border-amber-300 bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-800">
                Experimental Mode
            </span>
        </header>

        <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <form method="GET" action="{{ route('admin.evaluation-lab.index') }}" class="grid gap-3 xl:grid-cols-[280px_320px_260px_170px_120px_auto_auto]">
                <select name="institution_id" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                    <option value="">Pilih Institusi</option>
                    @foreach ($institutions as $institution)
                        <option value="{{ $institution->id }}" @selected((int) ($filters['institution_id'] ?? 0) === (int) $institution->id)>
                            {{ $institution->name }} ({{ $institution->type }})
                        </option>
                    @endforeach
                </select>

                <select name="period_id" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                    <option value="">Pilih Period Magang</option>
                    @foreach ($periodOptions as $period)
                        <option value="{{ $period->id }}" @selected((int) ($filters['period_id'] ?? 0) === (int) $period->id)>
                            {{ $period->name }} ({{ optional($period->start_date)->toDateString() }} - {{ optional($period->end_date)->toDateString() }})
                        </option>
                    @endforeach
                </select>

                <select name="intern_id" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                    <option value="">Semua Intern</option>
                    @foreach ($internOptions as $intern)
                        <option value="{{ $intern->id }}" @selected((int) ($filters['intern_id'] ?? 0) === (int) $intern->id)>
                            {{ $intern->name }}
                        </option>
                    @endforeach
                </select>

                <select name="sort" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                    <option value="final_score" @selected(($filters['sort'] ?? '') === 'final_score')>Sort: Final Score</option>
                    <option value="name" @selected(($filters['sort'] ?? '') === 'name')>Sort: Name</option>
                    <option value="missing_days" @selected(($filters['sort'] ?? '') === 'missing_days')>Sort: Missing Days</option>
                    <option value="submitted_days" @selected(($filters['sort'] ?? '') === 'submitted_days')>Sort: Submitted Days</option>
                </select>

                <select name="direction" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                    <option value="desc" @selected(($filters['direction'] ?? '') === 'desc')>DESC</option>
                    <option value="asc" @selected(($filters['direction'] ?? '') === 'asc')>ASC</option>
                </select>

                <button type="submit" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold hover:bg-slate-50">Terapkan</button>
                <a href="{{ route('admin.evaluation-lab.index') }}" class="rounded-xl border border-slate-300 px-4 py-2.5 text-center text-sm hover:bg-slate-50">Reset</a>
            </form>
        </article>

        @if ($selectedPeriod)
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-slate-500">Periode Aktif untuk Simulasi</p>
                <p class="mt-1 text-sm font-semibold text-slate-800">{{ $selectedPeriod->name }}</p>
                <p class="mt-1 text-xs text-slate-500">
                    Rentang: {{ optional($selectedPeriod->start_date)->toDateString() }} - {{ optional($selectedPeriod->end_date)->toDateString() }}
                    | Hari kerja wajib isi logbook: {{ $requiredDatesCount }} hari
                </p>
            </article>
        @endif

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <article class="kpi-card p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">Intern Dievaluasi</p>
                <p class="mt-1 text-2xl font-semibold">{{ $aggregate['intern_count'] ?? 0 }}</p>
            </article>
            <article class="kpi-card p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">Total Hari Wajib</p>
                <p class="mt-1 text-2xl font-semibold">{{ $aggregate['required_days_total'] ?? 0 }}</p>
            </article>
            <article class="kpi-card p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">Submit Logbook</p>
                <p class="mt-1 text-2xl font-semibold text-emerald-700">{{ $aggregate['submitted_days_total'] ?? 0 }}</p>
            </article>
            <article class="kpi-card p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">Missing Logbook</p>
                <p class="mt-1 text-2xl font-semibold text-rose-700">{{ $aggregate['missing_days_total'] ?? 0 }}</p>
            </article>
            <article class="kpi-card p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">Rata-rata Final Score</p>
                <p class="mt-1 text-2xl font-semibold text-blue-700">{{ number_format((float) ($aggregate['avg_final_score'] ?? 0), 2) }}</p>
            </article>
        </div>

        <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-slate-600">
                    <tr>
                        <th class="px-4 py-3">No</th>
                        <th class="px-4 py-3">Intern</th>
                        <th class="px-4 py-3">Hari Wajib</th>
                        <th class="px-4 py-3">Submitted</th>
                        <th class="px-4 py-3">Missing</th>
                        <th class="px-4 py-3">Zero Days</th>
                        <th class="px-4 py-3">Final Score</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($rows as $row)
                        <tr>
                            <td class="px-4 py-3">{{ $loop->iteration }}</td>
                            <td class="px-4 py-3 font-medium text-slate-700">{{ $row['name'] }}</td>
                            <td class="px-4 py-3">{{ $row['required_days'] }}</td>
                            <td class="px-4 py-3 text-emerald-700">{{ $row['submitted_days'] }}</td>
                            <td class="px-4 py-3 text-rose-700">{{ $row['missing_days'] }}</td>
                            <td class="px-4 py-3">{{ $row['zero_days'] }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold">
                                    {{ number_format((float) $row['final_score'], 2) }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-6 text-center text-sm text-slate-500">
                                Tidak ada data evaluasi. Pilih institusi dan periode magang terlebih dahulu.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-semibold">Detail Harian {{ $detailInternName ? '- '.$detailInternName : '' }}</h2>
            <p class="mt-1 text-xs text-slate-500">Hari kerja non-libur tanpa logbook akan otomatis bernilai 0.</p>

            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-slate-600">
                        <tr>
                            <th class="px-3 py-2">No</th>
                            <th class="px-3 py-2">Tanggal</th>
                            <th class="px-3 py-2">Logbook</th>
                            <th class="px-3 py-2">Task</th>
                            <th class="px-3 py-2">Score</th>
                            <th class="px-3 py-2">Catatan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($detailRows as $daily)
                            <tr>
                                <td class="px-3 py-2">{{ $loop->iteration }}</td>
                                <td class="px-3 py-2">{{ $daily['date'] }}</td>
                                <td class="px-3 py-2">{{ $daily['has_logbook'] ? 'Ada' : 'Tidak Ada' }}</td>
                                <td class="px-3 py-2">{{ $daily['task_count'] }}</td>
                                <td class="px-3 py-2">
                                    <span class="rounded-full px-2 py-1 text-xs font-semibold {{ (float) $daily['score'] <= 0.01 ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700' }}">
                                        {{ number_format((float) $daily['score'], 2) }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-xs text-slate-500">{{ $daily['note'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-3 py-6 text-center text-slate-500">Belum ada detail harian untuk intern terpilih.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </article>
    </section>
@endsection
