@extends('layouts.app')

@section('content')
    <section class="space-y-6">
        <header>
            <h1 class="text-2xl font-semibold tracking-tight">Admin Dashboard</h1>
            <p class="mt-1 text-sm text-slate-500">Kelola master data, assignment project spec, dan monitoring task harian intern.</p>
        </header>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm text-slate-500">Users</p>
                <p class="mt-1 text-3xl font-semibold">{{ $stats['users'] }}</p>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm text-slate-500">Roles</p>
                <p class="mt-1 text-3xl font-semibold">{{ $stats['roles'] }}</p>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm text-slate-500">Institutions</p>
                <p class="mt-1 text-3xl font-semibold">{{ $stats['institutions'] }}</p>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm text-slate-500">Periods</p>
                <p class="mt-1 text-3xl font-semibold">{{ $stats['periods'] }}</p>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm text-slate-500">Project Specs</p>
                <p class="mt-1 text-3xl font-semibold">{{ $stats['project_specs'] }}</p>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm text-slate-500">Tasks</p>
                <p class="mt-1 text-3xl font-semibold">{{ $stats['tasks'] }}</p>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm text-slate-500">Logbooks</p>
                <p class="mt-1 text-3xl font-semibold">{{ $stats['logbooks'] }}</p>
            </article>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <a href="{{ route('admin.roles.index') }}" class="rounded-2xl border border-slate-200 bg-white p-5 text-sm font-medium shadow-sm hover:bg-slate-50">Kelola Roles</a>
            <a href="{{ route('admin.users.index') }}" class="rounded-2xl border border-slate-200 bg-white p-5 text-sm font-medium shadow-sm hover:bg-slate-50">Kelola Users</a>
            <a href="{{ route('admin.institutions.index') }}" class="rounded-2xl border border-slate-200 bg-white p-5 text-sm font-medium shadow-sm hover:bg-slate-50">Kelola Institutions</a>
            <a href="{{ route('admin.periods.index') }}" class="rounded-2xl border border-slate-200 bg-white p-5 text-sm font-medium shadow-sm hover:bg-slate-50">Kelola Periods</a>
            <a href="{{ route('admin.projects.index') }}" class="rounded-2xl border border-slate-200 bg-white p-5 text-sm font-medium shadow-sm hover:bg-slate-50">Kelola Projects</a>
        </div>
    </section>
@endsection
