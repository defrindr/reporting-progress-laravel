<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Internship Logbook' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[radial-gradient(circle_at_top,#dbeafe,#f8fafc_35%,#f1f5f9)] text-slate-900">
    <div class="mx-auto flex min-h-screen w-full max-w-375 gap-4 p-4 sm:p-6">
        <aside class="w-full max-w-70 rounded-3xl border border-white/50 bg-white/45 p-5 shadow-xl shadow-slate-900/5 backdrop-blur-xl">
            <a href="{{ route('dashboard') }}" class="mb-6 block text-xl font-semibold tracking-tight">Internship Logbook</a>

            @if (auth()->check())
                <div class="mb-5 rounded-2xl border border-slate-200/70 bg-white/70 px-4 py-3">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Signed in</p>
                    <p class="mt-1 text-sm font-semibold">{{ auth()->user()->name }}</p>
                    <p class="text-xs text-slate-500">{{ auth()->user()->email }}</p>
                </div>

                <nav class="space-y-1 text-sm">
                    @if (auth()->user()->isAdmin())
                        <a href="{{ route('admin.dashboard') }}" class="block rounded-xl px-3 py-2 hover:bg-white/80">Admin Dashboard</a>
                        <a href="{{ route('admin.users.index') }}" class="block rounded-xl px-3 py-2 hover:bg-white/80">CRUD Users</a>
                        <a href="{{ route('admin.roles.index') }}" class="block rounded-xl px-3 py-2 hover:bg-white/80">CRUD Roles</a>
                        <a href="{{ route('admin.institutions.index') }}" class="block rounded-xl px-3 py-2 hover:bg-white/80">CRUD Institutions</a>
                        <a href="{{ route('admin.periods.index') }}" class="block rounded-xl px-3 py-2 hover:bg-white/80">CRUD Periods</a>
                        <a href="{{ route('admin.projects.index') }}" class="block rounded-xl px-3 py-2 hover:bg-white/80">Project Specs</a>
                    @endif

                    <a href="{{ route('logbook.form') }}" class="block rounded-xl px-3 py-2 hover:bg-white/80">Logbook</a>
                    <a href="{{ route('projects.board') }}" class="block rounded-xl px-3 py-2 hover:bg-white/80">Task Board</a>
                </nav>

                <form method="POST" action="{{ route('logout') }}" class="mt-6">
                    @csrf
                    <button type="submit" class="w-full rounded-xl bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-700">Logout</button>
                </form>
            @endif
        </aside>

        <main class="flex-1 rounded-3xl border border-white/60 bg-white/55 p-4 shadow-xl shadow-slate-900/5 backdrop-blur-xl sm:p-6">
            <header class="mb-5 rounded-2xl border border-slate-200/80 bg-white/80 px-4 py-3">
                <h1 class="text-base font-semibold tracking-tight">{{ $title ?? 'Workspace' }}</h1>
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
</body>
</html>
