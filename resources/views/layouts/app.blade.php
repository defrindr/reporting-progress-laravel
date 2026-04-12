<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Internship Logbook' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex w-full max-w-7xl items-center justify-between px-4 py-3 sm:px-6 lg:px-8">
            <a href="{{ route('dashboard') }}" class="text-lg font-semibold tracking-tight">Internship Logbook</a>
            <div class="flex items-center gap-3 text-sm">
                @if (auth()->check())
                    @if (auth()->user()->isAdmin())
                        <a href="{{ route('admin.dashboard') }}" class="rounded-lg px-3 py-1.5 hover:bg-slate-100">Admin</a>
                        <a href="{{ route('admin.users.index') }}" class="rounded-lg px-3 py-1.5 hover:bg-slate-100">Users</a>
                        <a href="{{ route('admin.roles.index') }}" class="rounded-lg px-3 py-1.5 hover:bg-slate-100">Roles</a>
                        <a href="{{ route('admin.projects.index') }}" class="rounded-lg px-3 py-1.5 hover:bg-slate-100">Projects</a>
                    @endif
                    <a href="{{ route('logbook.form') }}" class="rounded-lg px-3 py-1.5 hover:bg-slate-100">Logbook</a>
                    <a href="{{ route('projects.board') }}" class="rounded-lg px-3 py-1.5 hover:bg-slate-100">Board</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="rounded-lg bg-slate-900 px-3 py-1.5 font-medium text-white hover:bg-slate-700">Logout</button>
                    </form>
                @endif
            </div>
        </div>
    </header>

    <main class="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
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
</body>
</html>
