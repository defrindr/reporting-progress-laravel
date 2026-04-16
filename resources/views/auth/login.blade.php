<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - Internship Logbook</title>
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
<body class="min-h-screen text-slate-900">
    <div class="fixed right-4 top-4 z-40">
        <button
            type="button"
            data-theme-toggle
            class="glass inline-flex items-center rounded-xl border border-white/20 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-white/20"
        >
            <span data-theme-label>Dark Mode</span>
        </button>
    </div>

    <div class="mx-auto flex min-h-screen w-full max-w-6xl items-center justify-center px-4 py-10 sm:px-6 lg:px-8">
        <div class="grid w-full overflow-hidden rounded-3xl glass lg:grid-cols-2">
            <section class="relative hidden bg-slate-900/80 p-10 text-white lg:block">
                <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(29,84,109,0.4),transparent_55%)]"></div>
                <div class="absolute inset-0 bg-[radial-gradient(circle_at_bottom_left,rgba(56,189,248,0.15),transparent_45%)]"></div>
                <div class="relative z-10">
                    <p class="inline-flex rounded-full border border-white/30 px-3 py-1 text-xs tracking-wide backdrop-blur-sm">Internship Platform</p>
                    <h1 class="mt-6 text-4xl font-semibold leading-tight">Satu sistem untuk logbook, project, dan reporting institusi.</h1>
                    <ul class="mt-8 space-y-3 text-sm text-slate-200">
                        <li class="flex items-center gap-2">
                            <span class="inline-flex h-1.5 w-1.5 rounded-full bg-cyan-400"></span>
                            Role-based access (Admin, Supervisor, Intern)
                        </li>
                        <li class="flex items-center gap-2">
                            <span class="inline-flex h-1.5 w-1.5 rounded-full bg-cyan-400"></span>
                            Tracking harian per periode magang
                        </li>
                        <li class="flex items-center gap-2">
                            <span class="inline-flex h-1.5 w-1.5 rounded-full bg-cyan-400"></span>
                            Task board dengan comment dan history
                        </li>
                    </ul>
                </div>
            </section>

            <section class="p-6 sm:p-10">
                <div class="!border-0 glass rounded-2xl p-6 sm:p-8">
                    <h2 class="text-2xl font-semibold tracking-tight">Masuk ke Akun</h2>
                    <p class="mt-1 text-sm text-slate-500">Gunakan email dan password yang sudah terdaftar.</p>

                    @if ($errors->any())
                        <div class="mt-5 rounded-xl border border-rose-200/50 bg-rose-50/50 px-4 py-3 text-sm text-rose-700 backdrop-blur-sm">
                            {{ $errors->first() }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('login.store') }}" class="mt-6 space-y-4">
                        @csrf

                        <div>
                            <label for="email" class="mb-2 block text-sm font-medium text-slate-700">Email</label>
                            <input
                                id="email"
                                name="email"
                                type="email"
                                value="{{ old('email') }}"
                                required
                                autofocus
                                class="glass-input w-full text-slate-900"
                                placeholder="nama@institusi.ac.id"
                            >
                        </div>

                        <div>
                            <label for="password" class="mb-2 block text-sm font-medium text-slate-700">Password</label>
                            <input
                                id="password"
                                name="password"
                                type="password"
                                required
                                class="glass-input w-full text-slate-900"
                                placeholder="••••••••"
                            >
                        </div>

<label class="inline-flex items-center gap-2 text-sm text-slate-600">
                        <input type="checkbox" name="remember" value="1" class="h-4 w-4 rounded text-[#1D546D] focus:ring-[#1D546D]">
                        Ingat saya
                    </label>

                    <button
                        type="submit"
                        class="inline-flex w-full items-center justify-center rounded-xl bg-[#1D546D] px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-[#163F52] hover:shadow-lg hover:shadow-[#1D546D]/20"
                    >
                        Login
                    </button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
