{{-- 422 as a page: e.g. abort(422). Failed form validation never gets here — it redirects back with errors. --}}
<x-layouts.app :title="__('ui.errors.unprocessable.title')">
    <x-card class="text-center">
        <p class="text-6xl font-semibold text-stone-300">422</p>
        <p class="mt-2 text-stone-600">{{ __('ui.errors.unprocessable.text') }}</p>
        <a href="{{ route('home') }}" class="mt-4 inline-block text-amber-700 hover:underline">{{ __('ui.errors.back_home') }}</a>
    </x-card>
</x-layouts.app>
