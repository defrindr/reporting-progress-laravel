@extends('layouts.app')

@section('content')
    <section class="grid gap-6 lg:grid-cols-2">
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h1 class="text-2xl font-semibold tracking-tight">Form Logbook Harian</h1>
            <p class="mt-1 text-sm text-slate-500">Isi report aktivitas harian intern. Appendix dikirim sebagai tautan Drive/URL.</p>

            <form method="POST" action="{{ route('logbook.store') }}" class="mt-5 space-y-4">
                @csrf

                <div>
                    <label for="report_date" class="mb-2 block text-sm font-medium text-slate-700">Tanggal Report</label>
                    <input id="report_date" name="report_date" type="date" value="{{ old('report_date') }}" required class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                </div>

                <div>
                    <label for="done_tasks" class="mb-2 block text-sm font-medium text-slate-700">Sudah Dikerjakan</label>
                    <textarea id="done_tasks" name="done_tasks" rows="4" required class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">{{ old('done_tasks') }}</textarea>
                </div>

                <div>
                    <label for="next_tasks" class="mb-2 block text-sm font-medium text-slate-700">Akan Dikerjakan</label>
                    <textarea id="next_tasks" name="next_tasks" rows="4" required class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">{{ old('next_tasks') }}</textarea>
                </div>

                <div>
                    <label for="appendix_link" class="mb-2 block text-sm font-medium text-slate-700">Appendix Link (Opsional)</label>
                    <input id="appendix_link" name="appendix_link" type="url" value="{{ old('appendix_link') }}" placeholder="https://drive.google.com/..." class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                </div>

                <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">Submit Report</button>
            </form>
        </article>

        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold">Riwayat Logbook Saya</h2>
            <div class="mt-4 space-y-3">
                @forelse ($logbooks as $logbook)
                    <div class="rounded-xl border border-slate-200 p-3">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-sm font-semibold">{{ optional($logbook->report_date)->toDateString() }}</p>
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium">{{ $logbook->status }}</span>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">Periode: {{ $logbook->period?->name ?? '-' }}</p>
                        <p class="mt-3 text-sm"><span class="font-medium">Done:</span> {{ $logbook->done_tasks }}</p>
                        <p class="mt-1 text-sm"><span class="font-medium">Next:</span> {{ $logbook->next_tasks }}</p>
                        @if ($logbook->appendix_link)
                            <a href="{{ $logbook->appendix_link }}" target="_blank" class="mt-2 inline-block text-xs font-medium text-slate-900 underline underline-offset-4">Buka Appendix Link</a>
                        @endif
                    </div>
                @empty
                    <p class="rounded-xl border border-dashed border-slate-300 px-3 py-6 text-center text-sm text-slate-500">Belum ada logbook.</p>
                @endforelse
            </div>
        </article>
    </section>
@endsection
