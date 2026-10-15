{{--
    Legacy layout (template inheritance): child views use @extends('layouts.legacy')
    and fill @yield placeholders with @section. Kept only for the /about comparison
    page; everything else uses the <x-layouts.app> component.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Page') — {{ config('app.name') }}</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-stone-50 font-sans text-stone-800 antialiased">
    <header class="border-b border-stone-200 bg-white">
        <nav class="mx-auto flex max-w-3xl gap-6 px-4 py-3">
            <a href="{{ route('home') }}" class="font-semibold text-amber-700">{{ config('app.name') }}</a>
            <a href="{{ route('books.index') }}" class="text-sm">Catalog</a>
        </nav>
    </header>

    <main class="mx-auto max-w-3xl px-4 py-6">
        <h1 class="mb-4 text-2xl font-semibold">@yield('title')</h1>
        @yield('content')
    </main>
</body>
</html>
