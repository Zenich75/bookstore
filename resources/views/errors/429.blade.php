{{-- 429: a rate limiter said no (throttle:reviews, ...). --}}
<x-layouts.app :title="__('ui.errors.too_many_requests.title')">
    <x-card class="text-center">
        <p class="text-6xl font-semibold text-stone-300">429</p>
        <p class="mt-2 text-stone-600">{{ __('ui.errors.too_many_requests.text') }}</p>
        <a href="{{ route('home') }}" class="mt-4 inline-block text-amber-700 hover:underline">{{ __('ui.errors.back_home') }}</a>
    </x-card>
</x-layouts.app>
