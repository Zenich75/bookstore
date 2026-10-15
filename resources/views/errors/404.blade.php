{{-- Used by Route::fallback() and by Laravel for any 404 (e.g. ModelNotFoundException). --}}
<x-layouts.app :title="__('ui.errors.not_found.title')">
    <x-card class="text-center">
        <p class="text-6xl font-semibold text-stone-300">404</p>
        <p class="mt-2 text-stone-600">{{ __('ui.errors.not_found.text') }}</p>
        <a href="{{ route('books.index') }}" class="mt-4 inline-block text-amber-700 hover:underline">{{ __('ui.errors.not_found.back') }}</a>
    </x-card>
</x-layouts.app>
