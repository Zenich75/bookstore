<x-layouts.app>
    <section class="mb-8">
        <h1 class="text-3xl font-semibold">Bookstore Warehouse</h1>
        <p class="mt-2 text-stone-600">A small catalog and stock overview built step by step with Laravel.</p>
    </section>

    <section class="mb-10 grid grid-cols-2 gap-4 md:grid-cols-4">
        @foreach ($counts as $label => $count)
            <x-card class="text-center">
                <div class="text-2xl font-semibold">{{ number_format($count) }}</div>
                <div class="text-sm text-stone-500">{{ $label }}</div>
            </x-card>
        @endforeach
    </section>

    <section>
        <div class="mb-4 flex items-baseline justify-between">
            <h2 class="text-xl font-semibold">Latest books</h2>
            <a href="{{ route('books.index') }}" class="text-sm text-amber-700 hover:underline">Browse the catalog →</a>
        </div>

        <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
            @forelse ($latestBooks as $book)
                <x-book-card :book="$book" compact />
            @empty
                <p class="col-span-full text-stone-500">No books yet — run <code>sail artisan db:seed</code>.</p>
            @endforelse
        </div>
    </section>
</x-layouts.app>
