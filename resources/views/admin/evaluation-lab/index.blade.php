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
                <h1 class="text-2xl font-semibold tracking-tight">Evaluation Lab</h1>
                <p class="mt-1 text-sm text-slate-500">
                    Simulasi scoring akhir magang berbasis logbook + task. Aturan inti: hari kerja non-libur tanpa logbook = nilai 0.
                </p>
            </div>

            <span class="glass-badge text-amber-700">
                Experimental
            </span>
        </header>

        <details class="group glass rounded-2xl p-5" @if(request()->has('institution_id') || request()->has('period_id')) open @endif>
            <summary class="flex cursor-pointer items-center justify-between gap-2 text-sm font-semibold text-slate-800 [&::-webkit-details-marker]:hidden">
                <span>Filter & Pengaturan</span>
                <svg class="h-5 w-5 shrink-0 transition duration-300 group-open:-rotate-180" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 9l-7 7-7-7" />
                </svg>
            </summary>

            <form method="GET" action="{{ route('admin.evaluation-lab.index') }}" class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                <div>
                    <label class="mb-2 block text-xs font-semibold text-slate-600">Institusi</label>
                    <select name="institution_id" class="glass-input w-full">
                        <option value="">Semua Institusi</option>
                        @foreach ($institutions as $institution)
                            <option value="{{ $institution->id }}" @selected((int) ($filters['institution_id'] ?? 0) === (int) $institution->id)>
                                {{ $institution->name }} ({{ $institution->type }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-2 block text-xs font-semibold text-slate-600">Period Magang</label>
                    <select name="period_id" class="glass-input w-full">
                        <option value="">Semua Period</option>
                        @foreach ($periodOptions as $period)
                            <option value="{{ $period->id }}" @selected((int) ($filters['period_id'] ?? 0) === (int) $period->id)>
                                {{ $period->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-2 block text-xs font-semibold text-slate-600">Intern</label>
                    <select name="intern_id" class="glass-input w-full">
                        <option value="">Semua Intern</option>
                        @foreach ($internOptions as $intern)
                            <option value="{{ $intern->id }}" @selected((int) ($filters['intern_id'] ?? 0) === (int) $intern->id)>
                                {{ $intern->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-2 block text-xs font-semibold text-slate-600">Pengurutan</label>
                    <div class="grid grid-cols-2 gap-2">
                        <select name="sort" class="glass-input">
                            <option value="final_score" @selected(($filters['sort'] ?? '') === 'final_score')>Final Score</option>
                            <option value="name" @selected(($filters['sort'] ?? '') === 'name')>Nama</option>
                            <option value="missing_days" @selected(($filters['sort'] ?? '') === 'missing_days')>Missing</option>
                            <option value="submitted_days" @selected(($filters['sort'] ?? '') === 'submitted_days')>Submitted</option>
                        </select>
                        <select name="direction" class="glass-input">
                            <option value="desc" @selected(($filters['direction'] ?? '') === 'desc')>DESC</option>
                            <option value="asc" @selected(($filters['direction'] ?? '') === 'asc')>ASC</option>
                        </select>
                    </div>
                </div>

                <div class="flex items-end gap-2 xl:col-span-4">
                    <button type="submit" class="btn-primary">Terapkan Filter</button>
                    <a href="{{ route('admin.evaluation-lab.index') }}" class="btn-ghost">Reset</a>
                </div>
            </form>
        </details>

        @if ($selectedPeriod)
            <article class="glass rounded-2xl p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs uppercase tracking-wide text-slate-500">Periode Aktif</p>
                        <p class="mt-1 text-base font-semibold text-slate-800">{{ $selectedPeriod->name }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-xs text-slate-500">{{ optional($selectedPeriod->start_date)->toDateString() }} - {{ optional($selectedPeriod->end_date)->toDateString() }}</p>
                        <p class="mt-1 text-sm font-semibold text-[#1D546D]">{{ $requiredDatesCount }} hari kerja</p>
                    </div>
                </div>
            </article>
        @endif

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
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
                <p class="text-xs uppercase tracking-wide text-slate-500">Missing</p>
                <p class="mt-1 text-2xl font-semibold text-rose-700">{{ $aggregate['missing_days_total'] ?? 0 }}</p>
            </article>
            <article class="kpi-card p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">Avg Final Score</p>
                <p class="mt-1 text-2xl font-semibold text-[#1D546D]">{{ number_format((float) ($aggregate['avg_final_score'] ?? 0), 2) }}</p>
            </article>
        </div>

        <article class="glass overflow-x-auto rounded-2xl">
            <table class="min-w-full text-sm">
                <thead class="text-left text-slate-600">
                    <tr class="border-b border-slate-200">
                        <th class="px-4 py-3 font-semibold">No</th>
                        <th class="px-4 py-3 font-semibold">Intern</th>
                        <th class="px-4 py-3 font-semibold text-center">Hari Wajib</th>
                        <th class="px-4 py-3 font-semibold text-center">Submitted</th>
                        <th class="px-4 py-3 font-semibold text-center">Missing</th>
                        <th class="px-4 py-3 font-semibold text-center">Zero Days</th>
                        <th class="px-4 py-3 font-semibold text-center">Final Score</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($rows as $row)
                        <tr class="hover:bg-slate-50/50">
                            <td class="px-4 py-3 text-slate-500">{{ $loop->iteration }}</td>
                            <td class="px-4 py-3 font-medium text-slate-700">{{ $row['name'] }}</td>
                            <td class="px-4 py-3 text-center">{{ $row['required_days'] }}</td>
                            <td class="px-4 py-3 text-center text-emerald-700 font-semibold">{{ $row['submitted_days'] }}</td>
                            <td class="px-4 py-3 text-center text-rose-700 font-semibold">{{ $row['missing_days'] }}</td>
                            <td class="px-4 py-3 text-center">{{ $row['zero_days'] }}</td>
                            <td class="px-4 py-3 text-center">
                                @php($score = (float) $row['final_score'])
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold {{ $score >= 80 ? 'bg-emerald-100 text-emerald-700' : ($score >= 60 ? 'bg-amber-100 text-amber-700' : 'bg-rose-100 text-rose-700') }}">
                                    {{ number_format($score, 2) }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-slate-500">
                                Tidak ada data. Pilih institusi dan periode terlebih dahulu.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </article>

        @if($detailInternName)
            <article class="glass rounded-2xl p-5">
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-semibold">Detail Harian: {{ $detailInternName }}</h2>
                        <p class="mt-1 text-xs text-slate-500">Hari kerja non-libur tanpa logbook = 0</p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-slate-600">
                            <tr class="border-b border-slate-200">
                                <th class="px-3 py-2 font-semibold">#</th>
                                <th class="px-3 py-2 font-semibold">Tanggal</th>
                                <th class="px-3 py-2 font-semibold">Logbook</th>
                                <th class="px-3 py-2 font-semibold text-center">Task</th>
                                <th class="px-3 py-2 font-semibold text-center">Score</th>
                                <th class="px-3 py-2 font-semibold">Catatan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($detailRows as $daily)
                                <tr class="hover:bg-slate-50/50">
                                    <td class="px-3 py-2 text-slate-500">{{ $loop->iteration }}</td>
                                    <td class="px-3 py-2 font-medium">{{ $daily['date'] }}</td>
                                    <td class="px-3 py-2">
                                        @if($daily['has_logbook'])
                                            <span class="text-emerald-600">Submitted</span>
                                        @else
                                            <span class="text-rose-600">Missing</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-center">{{ $daily['task_count'] }}</td>
                                    <td class="px-3 py-2 text-center">
                                        @php($score = (float) $daily['score'])
                                        <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold {{ $score <= 0.01 ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700' }}">
                                            {{ number_format($score, 2) }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-xs text-slate-500">{{ $daily['note'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-3 py-6 text-center text-slate-500">Belum ada detail.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </article>
        @endif
    </section>
@endsection