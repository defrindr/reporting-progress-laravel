@extends('layouts.app')

@section('content')
    <section class="space-y-6">
        <header>
            <h1 class="text-2xl font-semibold tracking-tight">Dashboard Intern</h1>
            <p class="mt-1 text-sm text-slate-500">Ringkasan performa task, progres mingguan, dan insight fokus kerja.</p>
        </header>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article class="kpi-card p-5">
                <p class="text-xs uppercase tracking-wide text-slate-500">Task Belum Selesai</p>
                <p class="mt-2 text-3xl font-semibold text-slate-900">{{ $kpi['open_tasks'] }}</p>
            </article>
            <article class="kpi-card p-5">
                <p class="text-xs uppercase tracking-wide text-slate-500">Task Selesai (All Time)</p>
                <p class="mt-2 text-3xl font-semibold text-emerald-700">{{ $kpi['done_all_time'] }}</p>
            </article>
            <article class="kpi-card p-5">
                <p class="text-xs uppercase tracking-wide text-slate-500">Task Selesai (Minggu Ini)</p>
                <p class="mt-2 text-3xl font-semibold text-blue-700">{{ $kpi['done_this_week'] }}</p>
            </article>
            <article class="kpi-card p-5">
                <p class="text-xs uppercase tracking-wide text-slate-500">Task Selesai (Bulan Ini)</p>
                <p class="mt-2 text-3xl font-semibold text-violet-700">{{ $kpi['done_this_month'] }}</p>
            </article>
            <article class="kpi-card p-5">
                <p class="text-xs uppercase tracking-wide text-slate-500">Open Overdue</p>
                <p class="mt-2 text-3xl font-semibold text-rose-700">{{ $kpi['overdue_open'] }}</p>
            </article>
            <article class="kpi-card p-5">
                <p class="text-xs uppercase tracking-wide text-slate-500">Due 3 Hari Ke Depan</p>
                <p class="mt-2 text-3xl font-semibold text-amber-600">{{ $kpi['due_soon'] }}</p>
            </article>
            <article class="kpi-card p-5">
                <p class="text-xs uppercase tracking-wide text-slate-500">Completion Rate</p>
                <p class="mt-2 text-3xl font-semibold text-cyan-700">{{ number_format($kpi['completion_rate'], 1) }}%</p>
            </article>
            <article class="kpi-card p-5">
                <p class="text-xs uppercase tracking-wide text-slate-500">Total Komentar Kolaborasi</p>
                <p class="mt-2 text-3xl font-semibold text-slate-700">{{ $kpi['comment_count'] }}</p>
            </article>
        </div>

        <div class="grid gap-5 xl:grid-cols-3">
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm xl:col-span-1">
                <h2 class="text-base font-semibold">Status Task</h2>
                <p class="mt-1 text-xs text-slate-500">Distribusi task berdasarkan status.</p>
                <div class="mt-4 h-64">
                    <canvas id="internStatusChart"></canvas>
                </div>
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm xl:col-span-2">
                <h2 class="text-base font-semibold">Tren Penyelesaian 14 Hari</h2>
                <p class="mt-1 text-xs text-slate-500">Progress task done harian untuk evaluasi ritme kerja.</p>
                <div class="mt-4 h-64">
                    <canvas id="internDoneTrendChart"></canvas>
                </div>
            </article>
        </div>

        <div class="grid gap-5 xl:grid-cols-3">
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm xl:col-span-1">
                <h2 class="text-base font-semibold">Komposisi Prioritas</h2>
                <p class="mt-1 text-xs text-slate-500">Seberapa berat beban task berdasarkan level prioritas.</p>
                <div class="mt-4 h-72">
                    <canvas id="internPriorityChart"></canvas>
                </div>
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm xl:col-span-2">
                <h2 class="text-base font-semibold">Top Kontribusi Project</h2>
                <p class="mt-1 text-xs text-slate-500">Project yang paling banyak kamu kerjakan.</p>
                <div class="mt-4 h-72">
                    <canvas id="internProjectChart"></canvas>
                </div>
            </article>
        </div>

        <div class="grid gap-5 xl:grid-cols-2">
            <article class="overflow-x-auto rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-base font-semibold">Task Prioritas Saat Ini</h2>
                <table class="mt-3 min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-slate-600">
                        <tr>
                            <th class="px-3 py-2">Task</th>
                            <th class="px-3 py-2">Project</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2">Priority</th>
                            <th class="px-3 py-2">Due</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($focusTasks as $task)
                            <tr>
                                <td class="px-3 py-2 font-medium text-slate-700">{{ $task->title }}</td>
                                <td class="px-3 py-2">{{ $task->spec?->title ?? '-' }}</td>
                                <td class="px-3 py-2">{{ $task->status }}</td>
                                <td class="px-3 py-2">{{ $task->priority }}</td>
                                <td class="px-3 py-2">{{ optional($task->due_date)->toDateString() ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-3 py-6 text-center text-slate-500">Tidak ada task aktif saat ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-base font-semibold">Aktivitas Terbaru</h2>
                <ul class="mt-3 space-y-2 text-sm">
                    @forelse ($recentActivities as $activity)
                        <li class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                            <p class="font-medium text-slate-700">{{ $activity->description }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ optional($activity->created_at)->diffForHumans() }}</p>
                        </li>
                    @empty
                        <li class="rounded-xl border border-dashed border-slate-300 px-3 py-6 text-center text-slate-500">Belum ada aktivitas terbaru.</li>
                    @endforelse
                </ul>
            </article>
        </div>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        (function () {
            const statusCtx = document.getElementById('internStatusChart');
            const trendCtx = document.getElementById('internDoneTrendChart');
            const priorityCtx = document.getElementById('internPriorityChart');
            const projectCtx = document.getElementById('internProjectChart');

            if (!statusCtx || !trendCtx || !priorityCtx || !projectCtx || typeof Chart === 'undefined') {
                return;
            }

            new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: @json($charts['status_labels']),
                    datasets: [{
                        data: @json($charts['status_values']),
                        backgroundColor: ['#f59e0b', '#3b82f6', '#10b981'],
                        borderWidth: 1,
                    }],
                },
                options: {
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                        },
                    },
                },
            });

            new Chart(trendCtx, {
                type: 'line',
                data: {
                    labels: @json($charts['done_trend_labels']),
                    datasets: [{
                        label: 'Done',
                        data: @json($charts['done_trend_values']),
                        borderColor: '#2563eb',
                        backgroundColor: 'rgba(37, 99, 235, 0.12)',
                        fill: true,
                        tension: 0.28,
                    }],
                },
                options: {
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0,
                            },
                        },
                    },
                },
            });

            new Chart(priorityCtx, {
                type: 'bar',
                data: {
                    labels: @json($charts['priority_labels']),
                    datasets: [{
                        label: 'Jumlah Task',
                        data: @json($charts['priority_values']),
                        backgroundColor: ['#a3e635', '#22c55e', '#f97316', '#ef4444'],
                        borderRadius: 8,
                    }],
                },
                options: {
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0,
                            },
                        },
                    },
                    plugins: {
                        legend: {
                            display: false,
                        },
                    },
                },
            });

            new Chart(projectCtx, {
                type: 'bar',
                data: {
                    labels: @json($charts['project_labels']),
                    datasets: [{
                        label: 'Task',
                        data: @json($charts['project_values']),
                        backgroundColor: '#0f172a',
                        borderRadius: 8,
                    }],
                },
                options: {
                    indexAxis: 'y',
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0,
                            },
                        },
                    },
                    plugins: {
                        legend: {
                            display: false,
                        },
                    },
                },
            });
        })();
    </script>
@endsection
