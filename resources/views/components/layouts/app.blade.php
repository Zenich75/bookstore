{{-- Anonymous component layout: used as <x-layouts.app title="...">...</x-layouts.app>. --}}
@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title.' — ' : '' }}{{ config('app.name') }}</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-stone-50 font-sans text-stone-800 antialiased">
    <header class="border-b border-stone-200 bg-white">
        <nav class="mx-auto flex max-w-7xl flex-wrap items-center gap-x-6 gap-y-2 px-4 py-3">
            <a href="{{ route('home') }}" class="font-semibold text-amber-700">{{ config('app.name') }}</a>

            @php
                $links = [
                    'books.index' => __('ui.nav.catalog'),
                    'authors.index' => __('ui.nav.authors'),
                    'genres.index' => __('ui.nav.genres'),
                    'warehouses.index' => __('ui.nav.warehouses'),
                    'about' => __('ui.nav.about'),
                ];
            @endphp

            @foreach ($links as $routeName => $label)
                <a href="{{ route($routeName) }}"
                   @class([
                       'text-sm hover:text-amber-700',
                       'font-semibold text-amber-700' => request()->routeIs(Str::before($routeName, '.').'*'),
                   ])>{{ $label }}</a>
            @endforeach

            <span class="ml-auto flex gap-4 text-sm text-stone-500">
                <a href="{{ route('lab.relations') }}" class="hover:text-amber-700">{{ __('ui.nav.lab_relations') }}</a>
                <a href="{{ route('lab.n-plus-one') }}" class="hover:text-amber-700">{{ __('ui.nav.lab_n_plus_one') }}</a>
                <a href="{{ route('lab.collections') }}" class="hover:text-amber-700">{{ __('ui.nav.lab_collections') }}</a>
                <a href="{{ route('lab.models') }}" class="hover:text-amber-700">{{ __('ui.nav.lab_models') }}</a>
                <a href="{{ route('reports.stock') }}" class="hover:text-amber-700">{{ __('ui.nav.reports') }}</a>
                <a href="{{ route('admin.dashboard') }}" class="hover:text-amber-700">{{ __('ui.nav.admin') }}</a>
            </span>
        </nav>
    </header>

    {{-- Flash area: messages put into the session with ->with('status', '...'). --}}
    @if (session('status'))
        <div class="mx-auto mt-4 max-w-7xl px-4">
            <div class="rounded border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-800">
                {{ session('status') }}
            </div>
        </div>
    @endif

    <main class="mx-auto max-w-7xl px-4 py-6">
        @if ($title)
            <h1 class="mb-6 text-2xl font-semibold">{{ $title }}</h1>
        @endif

        {{ $slot }}
    </main>

    <footer class="mx-auto max-w-7xl px-4 py-8 text-xs text-stone-400">
        {{ __('ui.layout.tagline') }}
        {{-- @env: rendered only in the given environment(s). --}}
        @env('local')
            <span class="ml-2 rounded bg-amber-100 px-1.5 py-0.5 text-amber-800">{{ __('ui.layout.local_env') }}</span>
        @endenv
    </footer>

    {{-- Scripts pushed by pages/components with @push('scripts'). --}}
    @stack('scripts')
</body>
</html>
