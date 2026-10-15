{{-- Used by Route::fallback() and by Laravel for any 404 (e.g. ModelNotFoundException). --}}
<x-layouts.app title="Page not found">
    <x-card class="text-center">
        <p class="text-6xl font-semibold text-stone-300">404</p>
        <p class="mt-2 text-stone-600">We couldn't find that page.</p>
        <a href="{{ route('books.index') }}" class="mt-4 inline-block text-amber-700 hover:underline">Back to the catalog</a>
    </x-card>
</x-layouts.app>
