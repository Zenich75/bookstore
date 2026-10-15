<x-layouts.app :title="__('ui.admin.book_title', ['title' => $book->title])">
    @if ($book->trashed())
        <p class="mb-4 rounded border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-800">
            {{ __('ui.admin.soft_deleted', ['time' => $book->deleted_at->diffForHumans()]) }}
        </p>
    @endif

    <x-card>
        <dl class="grid grid-cols-[10rem_1fr] gap-y-1 text-sm">
            <dt class="text-stone-500">{{ __('ui.admin.fields.id') }}</dt><dd>{{ $book->id }}</dd>
            <dt class="text-stone-500">{{ __('ui.admin.fields.slug') }}</dt><dd>{{ $book->slug }}</dd>
            <dt class="text-stone-500">{{ __('ui.admin.fields.isbn') }}</dt><dd>{{ $book->isbn_formatted }}</dd>
            <dt class="text-stone-500">{{ __('ui.book.price') }}</dt><dd>{{ $book->price->format() }}</dd>
            <dt class="text-stone-500">{{ __('ui.admin.fields.genre') }}</dt><dd>{{ $book->genre->name }}</dd>
            <dt class="text-stone-500">{{ __('ui.admin.fields.authors') }}</dt><dd>{{ $book->authors->pluck('full_name')->join(', ') }}</dd>
            <dt class="text-stone-500">{{ __('ui.admin.fields.reviews') }}</dt><dd>{{ $book->reviews_count }}</dd>
            <dt class="text-stone-500">{{ __('ui.admin.fields.pending_reviews') }}</dt><dd>{{ $book->pending_reviews_count }}</dd>
        </dl>
        <x-slot:footer class="flex items-center justify-between">
            @unless ($book->trashed())
                <a href="{{ route('books.show', $book) }}" class="text-amber-700 hover:underline">{{ __('ui.admin.public_page') }}</a>
            @else
                <span></span>
            @endunless
            @include('admin.books._actions')
        </x-slot:footer>
    </x-card>
</x-layouts.app>
