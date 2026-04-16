@extends('layouts.app')

@section('content')
    <section class="space-y-6">
        <header>
            <h1 class="text-2xl font-semibold tracking-tight">Admin Dashboard</h1>
            <p class="mt-1 text-sm text-slate-500">Monitoring KPI intern, progres delivery task, kualitas eksekusi, dan konsistensi logbook.</p>
        </header>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <article class="kpi-card p-5">
                <p class="text-xs uppercase tracking-wide text-slate-500">Intern Aktif</p>
                <p class="mt-2 text-3xl font-semibold text-slate-900">{{ $kpi['active_interns'] }}</p>
                <p class="mt-2 text-xs text-slate-500">Intern pada institusi yang period magangnya sedang aktif.</p>
            </article>

            <article class="kpi-card p-5">
                <p class="text-xs uppercase tracking-wide text-slate-500">Task Completion</p>
                <p class="mt-2 text-3xl font-semibold text-emerald-700">{{ number_format($kpi['completion_rate'], 1) }}%</p>
                <p class="mt-2 text-xs text-slate-500">Rasio task selesai dari total task intern aktif.</p>
            </article>

            <article class="kpi-card p-5">
                <p class="text-xs uppercase tracking-wide text-slate-500">Open Overdue</p>
                <p class="mt-2 text-3xl font-semibold text-rose-700">{{ $kpi['overdue_open_tasks'] }}</p>
                <p class="mt-2 text-xs text-slate-500">Task lewat due date dengan status belum done.</p>
            </article>

            <article class="kpi-card p-5">
                <p class="text-xs uppercase tracking-wide text-slate-500">Logbook Today</p>
                <p class="mt-2 text-3xl font-semibold text-blue-700">{{ number_format($kpi['submission_rate_today'], 1) }}%</p>
                <p class="mt-2 text-xs text-slate-500">Coverage submit logbook hari ini terhadap intern aktif.</p>
            </article>

            <article class="kpi-card p-5">
                <p class="text-xs uppercase tracking-wide text-slate-500">Avg Done / Intern / 7 Hari</p>
                <p class="mt-2 text-3xl font-semibold text-violet-700">{{ number_format($kpi['avg_done_per_intern_week'], 2) }}</p>
                <p class="mt-2 text-xs text-slate-500">Produktivitas rata-rata task selesai tiap intern aktif.</p>
            </article>
        </div>

        <div class="grid gap-5 xl:grid-cols-3">
            <article class="glass rounded-2xl p-5 xl:col-span-1">
                <h2 class="text-base font-semibold">Distribusi Status Task</h2>
                <p class="mt-1 text-xs text-slate-500">Komposisi todo, doing, dan done untuk intern aktif.</p>
                <div class="mt-4 h-64">
                    <canvas id="taskStatusChart"></canvas>
                </div>
            </article>

            <article class="glass rounded-2xl p-5 xl:col-span-2">
                <h2 class="text-base font-semibold">Tren Task Selesai 14 Hari Terakhir</h2>
                <p class="mt-1 text-xs text-slate-500">Indikator ritme delivery intern secara harian.</p>
                <div class="mt-4 h-64">
                    <canvas id="doneTrendChart"></canvas>
                </div>
            </article>
        </div>

        <div class="grid gap-5 xl:grid-cols-3">
            <article class="glass rounded-2xl p-5 xl:col-span-1">
                <h2 class="text-base font-semibold">Top Produktivitas Intern</h2>
                <p class="mt-1 text-xs text-slate-500">Ranking berdasarkan jumlah task done.</p>
                <div class="mt-4 h-72">
                    <canvas id="topInternChart"></canvas>
                </div>
            </article>

            <article class="glass overflow-x-auto rounded-2xl p-5 xl:col-span-2">
                <div class="mb-3 flex items-center justify-between gap-2">
                    <h2 class="text-base font-semibold">Tabel KPI Intern</h2>
                    <span class="rounded-full bg-white/60 px-2.5 py-1 text-xs text-slate-600 backdrop-blur-sm">Weekly Snapshot</span>
                </div>

                <table class="min-w-full divide-y divide-white/20 text-sm">
                    <thead class="text-left text-slate-600">
                        <tr>
                            <th class="px-3 py-2">Intern</th>
                            <th class="px-3 py-2">Done</th>
                            <th class="px-3 py-2">Completion</th>
                            <th class="px-3 py-2">Open Overdue</th>
                            <th class="px-3 py-2">Logbook Minggu Ini</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/10">
                        @forelse ($topInterns as $intern)
                            <tr>
                                <td class="px-3 py-2 font-medium text-slate-700">{{ $intern['name'] }}</td>
                                <td class="px-3 py-2">{{ $intern['done_count'] }}</td>
                                <td class="px-3 py-2">{{ number_format($intern['completion_rate'], 1) }}%</td>
                                <td class="px-3 py-2">{{ $intern['overdue_open'] }}</td>
                                <td class="px-3 py-2">{{ $intern['weekly_logbooks'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-3 py-6 text-center text-slate-500">Belum ada data KPI intern aktif.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </article>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <article class="glass rounded-2xl p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">Total Users</p>
                <p class="mt-2 text-2xl font-semibold">{{ $stats['users'] }}</p>
            </article>
            <article class="glass rounded-2xl p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">Total Institutions</p>
                <p class="mt-2 text-2xl font-semibold">{{ $stats['institutions'] }}</p>
            </article>
            <article class="glass rounded-2xl p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">Total Project Specs</p>
                <p class="mt-2 text-2xl font-semibold">{{ $stats['project_specs'] }}</p>
            </article>
            <article class="glass rounded-2xl p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">Total Logbooks</p>
                <p class="mt-2 text-2xl font-semibold">{{ $stats['logbooks'] }}</p>
            </article>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <a href="{{ route('admin.users.index') }}" class="glass rounded-2xl p-5 text-sm font-medium hover:bg-white/20">Kelola Users</a>
            <a href="{{ route('admin.periods.index') }}" class="glass rounded-2xl p-5 text-sm font-medium hover:bg-white/20">Kelola Periods</a>
            <a href="{{ route('admin.projects.index') }}" class="glass rounded-2xl p-5 text-sm font-medium hover:bg-white/20">Kelola Projects</a>
        </div>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        (function () {
            const statusCtx = document.getElementById('taskStatusChart');
            const trendCtx = document.getElementById('doneTrendChart');
            const topInternCtx = document.getElementById('topInternChart');

            if (!statusCtx || !trendCtx || !topInternCtx || typeof Chart === 'undefined') {
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
                        label: 'Task Done',
                        data: @json($charts['done_trend_values']),
                        borderColor: '#1D546D',
                        backgroundColor: 'rgba(29, 84, 109, 0.12)',
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

            new Chart(topInternCtx, {
                type: 'bar',
                data: {
                    labels: @json($charts['top_intern_labels']),
                    datasets: [{
                        label: 'Done',
                        data: @json($charts['top_intern_done_values']),
                        backgroundColor: '#1D546D',
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