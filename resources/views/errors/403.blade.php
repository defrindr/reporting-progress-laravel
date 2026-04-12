<!DOCTYPE html>
<html lang="id" class="h-full">

    <head>
        <meta charset="UTF-8">
        <title>403 Forbidden</title>
        <script>
            (function() {
                const theme = localStorage.getItem('internship-theme');
                console.log(theme)

                if (theme) {
                    document.documentElement.setAttribute('data-theme', theme);
                } else {
                    const isDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                    document.documentElement.setAttribute('data-theme', isDark ? 'dark' : 'light');
                }
            })();
        </script>
        @vite('resources/css/app.css')
    </head>

    <body class="h-full bg-gray-100 dark:bg-gray-900 flex items-center justify-center">

        <div
            class="w-full max-w-md p-8 rounded-2xl shadow-lg 
                bg-white border border-gray-200 dark:border-gray-700
                text-center">

            <!-- Code -->
            <h1 class="text-4xl font-bold text-[#1d4ed8] mb-2">
                403
            </h1>

            <!-- Message -->
            <p class="text-gray-700 dark:text-gray-300 mb-6">
                {{ $exception->getMessage() ?: 'Akses ditolak.' }}
            </p>

            <!-- Action -->
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"
                    class="w-full px-4 py-2 rounded-lg font-medium text-white
                       bg-[#1d4ed8] hover:bg-blue-700
                       transition duration-200">
                    Logout
                </button>
            </form>

        </div>

    </body>

</html>
