@extends('layouts.app')

@section('content')
    <section class="space-y-6">
        <header>
            <h1 class="text-2xl font-semibold tracking-tight">Edit Password</h1>
            <p class="mt-1 text-sm text-slate-500">Perbarui password akun untuk menjaga keamanan akses.</p>
        </header>

        <article class="max-w-2xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <form method="POST" action="{{ route('profile.password.update') }}" class="space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label for="current_password" class="mb-2 block text-sm font-medium text-slate-700">Password Saat Ini</label>
                    <input id="current_password" name="current_password" type="password" required
                        class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                </div>

                <div>
                    <label for="password" class="mb-2 block text-sm font-medium text-slate-700">Password Baru</label>
                    <input id="password" name="password" type="password" minlength="8" required
                        class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                </div>

                <div>
                    <label for="password_confirmation" class="mb-2 block text-sm font-medium text-slate-700">Konfirmasi Password Baru</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" minlength="8" required
                        class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                </div>

                <button type="submit" class="btn-primary">Simpan Password</button>
            </form>
        </article>
    </section>
@endsection
