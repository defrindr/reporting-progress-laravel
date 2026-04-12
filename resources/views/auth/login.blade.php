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
<body class="min-h-screen bg-slate-100 text-slate-900">
    <div class="fixed right-4 top-4 z-40">
        <button
            type="button"
            data-theme-toggle
            class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:hover:bg-slate-700"
        >
            <span data-theme-label>Dark Mode</span>
        </button>
    </div>

    <div class="mx-auto flex min-h-screen w-full max-w-6xl items-center px-4 py-10 sm:px-6 lg:px-8">
        <div class="grid w-full overflow-hidden rounded-3xl bg-white shadow-xl lg:grid-cols-2">
            <section class="relative hidden bg-slate-900 p-10 text-white lg:block">
                <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(255,255,255,0.15),transparent_55%)]"></div>
                <div class="relative z-10">
                    <p class="inline-flex rounded-full border border-white/30 px-3 py-1 text-xs tracking-wide">Internship Platform</p>
                    <h1 class="mt-6 text-4xl font-semibold leading-tight">Satu sistem untuk logbook, project, dan reporting institusi.</h1>
                    <ul class="mt-8 space-y-3 text-sm text-slate-200">
                        <li>Role-based access (Admin, Supervisor, Intern)</li>
                        <li>Tracking harian per periode magang</li>
                        <li>Project board dengan comment dan history</li>
                    </ul>
                </div>
            </section>

            <section class="p-6 sm:p-10">
                <h2 class="text-2xl font-semibold tracking-tight">Masuk ke Akun</h2>
                <p class="mt-1 text-sm text-slate-500">Gunakan email dan password yang sudah terdaftar.</p>

                @if ($errors->any())
                    <div class="mt-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
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
                            class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-slate-900"
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
                            class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-slate-900"
                            placeholder="••••••••"
                        >
                    </div>

                    <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                        <input type="checkbox" name="remember" value="1" class="h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-slate-900">
                        Ingat saya
                    </label>

                    <button
                        type="submit"
                        class="inline-flex w-full items-center justify-center rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-700"
                    >
                        Login
                    </button>
                </form>
            </section>
        </div>
    </div>
</body>
</html>
