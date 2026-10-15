{{--
    Anonymous component. Expects a book loaded via BookController::cardQuery()
    (genre, authors, cover, approved_reviews_count/avg, stocks_sum_quantity).
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
       title="{{ $book->cover?->path ?? 'No cover' }}">
        {{ Str::limit($book->title, 40) }}
    </a>

    <div class="flex flex-1 flex-col gap-1 p-3">
        <a href="{{ route('books.show', $book) }}" class="font-medium leading-snug hover:text-amber-700">{{ $book->title }}</a>

        <p class="text-sm text-stone-500">
            {{ $book->authors->map(fn ($author) => $author->first_name.' '.$author->last_name)->join(', ') ?: 'Unknown author' }}
        </p>

        <x-rating-stars :rating="$book->approved_reviews_avg_rating" />

        <div class="mt-auto flex items-center justify-between pt-2 text-sm">
            {{-- Price is stored in cents; formatting moves to an accessor in stage 6. --}}
            <span class="font-semibold">{{ Number::currency($book->price / 100, 'USD') }}</span>
            <span @class([
                'text-xs',
                'text-red-600' => (int) $book->stocks_sum_quantity === 0,
                'text-stone-500' => (int) $book->stocks_sum_quantity > 0,
            ])>{{ (int) $book->stocks_sum_quantity }} in stock</span>
        </div>

        @unless ($compact)
            <p class="text-xs text-stone-400">
                {{ ucfirst($book->format->value) }} · {{ $book->genre->name }} · {{ $book->approved_reviews_count }} reviews
            </p>
        @endunless
    </div>
</article>
