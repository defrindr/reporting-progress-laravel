@extends('layouts.app')

@php
    $isManager = (bool) ($isManager ?? false);
@endphp

@section('content')
    <section class="grid gap-6 {{ $isManager ? '' : 'lg:grid-cols-2' }}">
        @if (!$isManager)
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h1 class="text-2xl font-semibold tracking-tight">Form Logbook Harian</h1>
                <p class="mt-1 text-sm text-slate-500">Isi report aktivitas harian intern. Appendix dikirim sebagai tautan Drive/URL.</p>

                @if (($isInternReadOnly ?? false) === true)
                    <div class="mt-4 rounded-xl border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">
                        <p class="font-semibold">Mode Read-Only</p>
                        <p class="mt-1">{{ $readOnlyReason ?? 'Tidak ada periode aktif untuk tanggal ini.' }}</p>
                    </div>
                @elseif (($isWeekendLock ?? false) === true)
                    <div class="mt-4 rounded-xl border border-sky-300 bg-sky-50 p-3 text-sm text-sky-900">
                        <p class="font-semibold">Mode Weekend</p>
                        <p class="mt-1">Tidak bisa buat report untuk Sabtu/Minggu. Kamu bisa lanjut update backlog minggu depan di project board.</p>
                    </div>
                @endif

                <form method="POST" action="{{ route('logbook.store') }}" class="mt-5 space-y-4">
                    @csrf

                    <div>
                        <label for="report_date" class="mb-2 block text-sm font-medium text-slate-700">Tanggal Report</label>
                        <input id="report_date" name="report_date" type="date" value="{{ old('report_date') }}" required @disabled(($isInternReadOnly ?? false) === true) class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm disabled:cursor-not-allowed disabled:bg-slate-100">
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                        <p class="text-sm font-semibold text-slate-800">Auto Resume dari Project Board</p>
                        <p class="mt-1 text-xs text-slate-500">Tarik task harian atau mingguan yang sudah dikerjakan, lalu tetap bisa kamu edit manual.</p>

                        <div class="mt-3 grid gap-2 lg:grid-cols-[1fr_1fr_auto]">
                            <select id="resume_scope" class="rounded-xl border border-slate-300 px-3 py-2 text-sm">
                                <option value="daily">Harian (tanggal report)</option>
                                <option value="weekly">Mingguan (Senin-Minggu)</option>
                            </select>

                            <label class="flex items-center gap-2 rounded-xl border border-slate-300 px-3 py-2 text-sm">
                                <input id="resume_use_ai" type="checkbox" class="rounded border-slate-300" checked>
                                AI lokal tanpa API key
                            </label>

                            <button id="generate_resume_button" type="button" class="rounded-xl border border-slate-300 px-3 py-2 text-sm hover:bg-slate-100">Tarik Resume</button>
                        </div>

                        <p id="resume_meta" class="mt-2 text-xs text-slate-500"></p>
                        <ul id="resume_task_preview" class="mt-2 space-y-1 text-xs text-slate-600"></ul>
                    </div>

                    <div>
                        <label for="done_tasks" class="mb-2 block text-sm font-medium text-slate-700">Sudah Dikerjakan</label>
                        <textarea id="done_tasks" name="done_tasks" rows="4" required @disabled(($isInternReadOnly ?? false) === true) class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm disabled:cursor-not-allowed disabled:bg-slate-100">{{ old('done_tasks') }}</textarea>
                    </div>

                    <div>
                        <label for="next_tasks" class="mb-2 block text-sm font-medium text-slate-700">Akan Dikerjakan</label>
                        <textarea id="next_tasks" name="next_tasks" rows="4" required @disabled(($isInternReadOnly ?? false) === true) class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm disabled:cursor-not-allowed disabled:bg-slate-100">{{ old('next_tasks') }}</textarea>
                    </div>

                    <div>
                        <label for="appendix_link" class="mb-2 block text-sm font-medium text-slate-700">Appendix Link (Opsional)</label>
                        <input id="appendix_link" name="appendix_link" type="url" value="{{ old('appendix_link') }}" placeholder="https://drive.google.com/..." @disabled(($isInternReadOnly ?? false) === true) class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm disabled:cursor-not-allowed disabled:bg-slate-100">
                    </div>

                    <button type="submit" @disabled(($isInternReadOnly ?? false) === true || ($isWeekendLock ?? false) === true) class="inline-flex w-full items-center justify-center rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700 disabled:cursor-not-allowed disabled:bg-slate-400">Submit Report</button>
                </form>
            </article>
        @endif

        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold">{{ $isManager ? 'Riwayat Logbook Intern' : 'Riwayat Logbook Saya' }}</h2>
            <div class="mt-4 space-y-3">
                @forelse ($logbooks as $logbook)
                    <div class="rounded-xl border border-slate-200 p-3">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-sm font-semibold">{{ optional($logbook->report_date)->toDateString() }}</p>
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium">{{ $logbook->status }}</span>
                        </div>
                        @if ($isManager)
                            <p class="mt-1 text-xs text-slate-500">Intern: {{ $logbook->user?->name ?? '-' }}</p>
                        @endif
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

    <script>
        (function () {
            const reportDateInput = document.getElementById('report_date');
            const scopeInput = document.getElementById('resume_scope');
            const useAiInput = document.getElementById('resume_use_ai');
            const generateButton = document.getElementById('generate_resume_button');
            const doneTasksInput = document.getElementById('done_tasks');
            const nextTasksInput = document.getElementById('next_tasks');
            const metaEl = document.getElementById('resume_meta');
            const previewEl = document.getElementById('resume_task_preview');
            const endpoint = @json(route('logbook.task-resume'));

            if (!generateButton) {
                return;
            }

            const escapeHtml = (value) => {
                return String(value)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            };

            generateButton.addEventListener('click', async () => {
                const reportDate = reportDateInput?.value;
                const scope = scopeInput?.value || 'daily';
                const useAi = useAiInput?.checked ? '1' : '0';

                if (!reportDate) {
                    alert('Pilih tanggal report dulu sebelum tarik resume.');
                    return;
                }

                if ((doneTasksInput.value.trim() !== '' || nextTasksInput.value.trim() !== '') && !confirm('Isi otomatis akan menimpa isi textarea saat ini. Lanjutkan?')) {
                    return;
                }

                generateButton.disabled = true;
                generateButton.textContent = 'Memproses...';
                metaEl.textContent = '';
                previewEl.innerHTML = '';

                try {
                    const params = new URLSearchParams({
                        report_date: reportDate,
                        scope,
                        use_ai: useAi,
                    });

                    const response = await fetch(`${endpoint}?${params.toString()}`, {
                        headers: {
                            'Accept': 'application/json',
                        },
                    });

                    if (!response.ok) {
                        const payload = await response.json().catch(() => ({}));
                        alert(payload.message || 'Gagal menarik resume task.');
                        return;
                    }

                    const payload = await response.json();

                    doneTasksInput.value = payload.done_tasks || '';
                    nextTasksInput.value = payload.next_tasks || '';

                    const meta = payload.meta || {};
                    metaEl.textContent = `Range ${meta.range || '-'} | sumber ${meta.generator || '-'} | total ${meta.total_tasks || 0} task (done ${meta.done || 0}, doing ${meta.doing || 0}, todo ${meta.todo || 0})`;

                    const tasks = Array.isArray(payload.tasks) ? payload.tasks : [];
                    previewEl.innerHTML = tasks.slice(0, 8).map((task) => {
                        const project = escapeHtml(task.project || '-');
                        const title = escapeHtml(task.title || '-');
                        const status = escapeHtml(task.status || '-');
                        const due = escapeHtml(task.due_date || '-');

                        return `<li class="rounded border border-slate-200 bg-white px-2 py-1.5">${title} | ${project} | status ${status} | due ${due}</li>`;
                    }).join('');
                } catch (error) {
                    alert('Terjadi error saat mengambil resume otomatis.');
                } finally {
                    generateButton.disabled = false;
                    generateButton.textContent = 'Tarik Resume';
                }
            });
        })();
    </script>
@endsection
