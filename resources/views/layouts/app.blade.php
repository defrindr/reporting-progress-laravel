<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Internship Logbook' }}</title>
    <script>
        (function () {
            try {
                const key = 'internship-theme';
                const stored = localStorage.getItem(key);
                const systemPrefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
                const theme = stored === 'dark' || stored === 'light'
                    ? stored
                    : (systemPrefersDark ? 'dark' : 'light');

                document.documentElement.setAttribute('data-theme', theme);
                document.documentElement.style.colorScheme = theme;
            } catch (error) {
                document.documentElement.setAttribute('data-theme', 'light');
                document.documentElement.style.colorScheme = 'light';
            }
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="text-slate-900">
    @php
        $authUser = auth()->user();
        $isAdmin = $authUser?->isAdmin() ?? false;
        $isManager = $authUser?->canManageAllProjects() ?? false;
        $isIntern = $authUser?->hasRole('Intern') ?? false;
        $dashboardRoute = $isAdmin
            ? route('admin.dashboard')
            : ($isIntern ? route('intern.dashboard') : route('projects.board'));
        $isDashboardActive = request()->routeIs('dashboard') || request()->routeIs('admin.dashboard') || request()->routeIs('intern.dashboard');
        $sidebarLinkClass = static fn (bool $active): string => $active ? 'app-sidebar-link app-sidebar-link-active' : 'app-sidebar-link';
    @endphp

    @if ($authUser)
        <div class="app-shell lg:pl-76">
            <aside id="app-sidebar" class="app-sidebar fixed inset-y-3 left-3 z-40 flex w-72 -translate-x-[120%] flex-col rounded-3xl border border-slate-800/80 p-5 shadow-2xl shadow-slate-950/45 transition-transform duration-300 lg:translate-x-0">
                <a href="{{ route('dashboard') }}" class="mb-6 block">
                    <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Internship Suite</p>
                    <p class="mt-1 text-xl font-semibold tracking-tight text-white">Progress Console</p>
                </a>

                <div class="mb-5 rounded-2xl border border-slate-700 bg-slate-900/45 px-4 py-3">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Signed In</p>
                    <p class="mt-1 text-sm font-semibold text-slate-100">{{ $authUser->name }}</p>
                    <p class="text-xs text-slate-400">{{ $authUser->email }}</p>
                </div>

                <nav class="space-y-1.5 text-sm">
                    <a href="{{ $dashboardRoute }}" class="{{ $sidebarLinkClass($isDashboardActive) }}">Dashboard</a>

                    @if ($isAdmin)
                        {{-- <a href="{{ route('admin.dashboard') }}" class="{{ $sidebarLinkClass(request()->routeIs('admin.dashboard')) }}">Admin Dashboard</a> --}}
                        <a href="{{ route('admin.users.index') }}" class="{{ $sidebarLinkClass(request()->routeIs('admin.users.*')) }}">Users</a>
                        <a href="{{ route('admin.roles.index') }}" class="{{ $sidebarLinkClass(request()->routeIs('admin.roles.*')) }}">Roles</a>
                        <a href="{{ route('admin.institutions.index') }}" class="{{ $sidebarLinkClass(request()->routeIs('admin.institutions.*')) }}">Institutions</a>
                        <a href="{{ route('admin.periods.index') }}" class="{{ $sidebarLinkClass(request()->routeIs('admin.periods.*')) }}">Periods</a>
                        <a href="{{ route('admin.projects.index') }}" class="{{ $sidebarLinkClass(request()->routeIs('admin.projects.*')) }}">Project Specs</a>
                        <a href="{{ route('admin.evaluation-lab.index') }}" class="{{ $sidebarLinkClass(request()->routeIs('admin.evaluation-lab.*')) }}">Evaluation Lab</a>
                    @endif

                    <a href="{{ route('projects.board') }}" class="{{ $sidebarLinkClass(request()->routeIs('projects.*')) }}">Task Board</a>
                    <a href="{{ route('logbook.form') }}" class="{{ $sidebarLinkClass(request()->routeIs('logbook.*')) }}">{{ $isManager ? 'Logbook Monitoring' : 'Logbook' }}</a>
                    <a href="{{ route('profile.password.edit') }}" class="{{ $sidebarLinkClass(request()->routeIs('profile.password.*')) }}">Edit Password</a>
                </nav>

                <form method="POST" action="{{ route('logout') }}" class="mt-auto pt-5">
                    @csrf
                    <button type="submit" class="w-full rounded-xl bg-slate-100 px-3 py-2 text-sm font-semibold text-slate-900 hover:bg-white">Logout</button>
                </form>
            </aside>

            <div id="sidebar-backdrop" class="fixed inset-0 z-30 hidden bg-slate-900/45 lg:hidden"></div>

            <main class="min-h-screen p-3 sm:p-5 lg:p-6">
                <header class="app-content-panel sticky top-3 z-20 mb-5 rounded-2xl px-4 py-3">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <button id="sidebar-toggle" type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-700 shadow-sm lg:hidden">
                                <span class="sr-only">Open menu</span>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h16M4 12h16M4 18h16" />
                                </svg>
                            </button>

                            <div>
                                <h1 class="text-base font-semibold tracking-tight">{{ $title ?? 'Workspace' }}</h1>
                                <p class="text-xs text-slate-500">{{ now()->translatedFormat('l, d F Y') }}</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                data-theme-toggle
                                class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50"
                            >
                                <span data-theme-label>Dark Mode</span>
                            </button>

                            <div class="hidden rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-600 sm:block">
                                {{ $isManager ? 'Mode Monitoring' : 'Mode Operasional' }}
                            </div>
                        </div>
                    </div>
                </header>

                @if (session('status'))
                    <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                        {{ session('status') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                        {{ $errors->first() }}
                    </div>
                @endif

                @yield('content')
            </main>
        </div>

        <script>
            (function() {
                const sidebar = document.getElementById('app-sidebar');
                const toggle = document.getElementById('sidebar-toggle');
                const backdrop = document.getElementById('sidebar-backdrop');

                if (!sidebar || !toggle || !backdrop) {
                    return;
                }

                const openSidebar = () => {
                    sidebar.classList.remove('-translate-x-[120%]');
                    backdrop.classList.remove('hidden');
                };

                const closeSidebar = () => {
                    if (window.innerWidth >= 1024) {
                        return;
                    }

                    sidebar.classList.add('-translate-x-[120%]');
                    backdrop.classList.add('hidden');
                };

                toggle.addEventListener('click', openSidebar);
                backdrop.addEventListener('click', closeSidebar);
                window.addEventListener('resize', () => {
                    if (window.innerWidth >= 1024) {
                        backdrop.classList.add('hidden');
                        sidebar.classList.remove('-translate-x-[120%]');
                    } else {
                        sidebar.classList.add('-translate-x-[120%]');
                    }
                });
            })();
        </script>
    @else
        @yield('content')
    @endif
</body>
</html>
