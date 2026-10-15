<x-layouts.app :title="'Admin: '.$book->title">
    @if ($book->trashed())
        <p class="mb-4 rounded border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-800">
            Soft-deleted {{ $book->deleted_at->diffForHumans() }} — hidden from the public catalog.
        </p>
    @endif

    <x-card>
        <dl class="grid grid-cols-[10rem_1fr] gap-y-1 text-sm">
            <dt class="text-stone-500">ID</dt><dd>{{ $book->id }}</dd>
            <dt class="text-stone-500">Slug</dt><dd>{{ $book->slug }}</dd>
            <dt class="text-stone-500">ISBN</dt><dd>{{ $book->isbn }}</dd>
            <dt class="text-stone-500">Genre</dt><dd>{{ $book->genre->name }}</dd>
            <dt class="text-stone-500">Authors</dt><dd>{{ $book->authors->map(fn ($a) => $a->first_name.' '.$a->last_name)->join(', ') }}</dd>
            <dt class="text-stone-500">Reviews</dt><dd>{{ $book->reviews_count }}</dd>
        </dl>
        @unless ($book->trashed())
            <x-slot:footer><a href="{{ route('books.show', $book) }}" class="text-amber-700 hover:underline">Public page →</a></x-slot:footer>
        @endunless
    </x-card>
</x-layouts.app>
