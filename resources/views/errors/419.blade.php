{{-- 419: CSRF token missing or expired (TokenMismatchException), e.g. a form left open past the session lifetime. --}}
<x-layouts.app :title="__('ui.errors.page_expired.title')">
    <x-card class="text-center">
        <p class="text-6xl font-semibold text-stone-300">419</p>
        <p class="mt-2 text-stone-600">{{ __('ui.errors.page_expired.text') }}</p>
        <a href="{{ route('home') }}" class="mt-4 inline-block text-amber-700 hover:underline">{{ __('ui.errors.back_home') }}</a>
    </x-card>
</x-layouts.app>
