{{--
    Anonymous component. Expects a book loaded via the Book::withCardData() scope
    (genre, authors, cover, reviews_count/avg, stocks_sum_quantity).
    Extra attributes passed by the caller (class, id, data-*) are merged in.
--}}
@props(['book', 'compact' => false])

<article {{ $attributes->merge(['class' => 'flex flex-col rounded-lg border border-stone-200 bg-white']) }}>
    {{-- Covers are placeholder paths without files, so we draw a placeholder. --}}
    <a href="{{ route('books.show', $book) }}"
       @class([
           'flex items-center justify-center rounded-t-lg bg-gradient-to-br from-amber-100 to-stone-200 px-3 text-center font-semibold text-stone-600',
           'h-28 text-sm' => $compact,
           'h-40' => ! $compact,
           'opacity-60' => ! $book->cover,
       ])
       title="{{ $book->cover?->path ?? __('ui.card.no_cover') }}">
        {{ Str::limit($book->title, 40) }}
    </a>

    <div class="flex flex-1 flex-col gap-1 p-3">
        <a href="{{ route('books.show', $book) }}" class="font-medium leading-snug hover:text-amber-700">{{ $book->title }}</a>

        <p class="text-sm text-stone-500">
            {{ $book->authors->pluck('full_name')->join(', ') ?: __('ui.card.unknown_author') }}
        </p>

        <x-rating-stars :rating="$book->reviews_avg_rating" />

        <div class="mt-auto flex items-center justify-between pt-2 text-sm">
            {{-- price is a Money value object (MoneyCast); kopecks are formatted only here. --}}
            <span class="font-semibold">{{ $book->price->format() }}</span>
            <span @class([
                'text-xs',
                'text-red-600' => (int) $book->stocks_sum_quantity === 0,
                'text-stone-500' => (int) $book->stocks_sum_quantity > 0,
            ])>{{ __('ui.card.in_stock', ['count' => (int) $book->stocks_sum_quantity]) }}</span>
        </div>

        @unless ($compact)
            <p class="text-xs text-stone-400">
                {{ $book->format->label() }} · {{ $book->genre->name }} · {{ trans_choice('ui.counts.reviews', $book->reviews_count) }}
            </p>
        @endunless
    </div>
</article>
