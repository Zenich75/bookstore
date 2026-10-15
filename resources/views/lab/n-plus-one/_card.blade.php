{{--
    The card every N+1 variant renders. It reads relations straight from Blade —
    which is exactly how N+1 hides: nothing here looks like a query.
--}}
@php
    // withCount() result when present; otherwise count the relation (lazy or eager-loaded).
    $reviewCount = array_key_exists('approved_reviews_count', $book->getAttributes())
        ? $book->approved_reviews_count
        : $book->approvedReviews->count();
@endphp

<article class="rounded border border-stone-200 bg-white p-3 text-sm">
    <div class="flex gap-3">
        <div class="flex h-16 w-12 shrink-0 items-center justify-center rounded bg-amber-100 text-[10px] text-stone-500"
             title="{{ $book->cover?->path ?? 'no cover' }}">{{ $book->cover ? 'cover' : '—' }}</div>
        <div class="min-w-0">
            <p class="truncate font-medium">{{ $book->title }}</p>
            <p class="truncate text-stone-500">
                {{-- LabBook (variant l): the author_names accessor hides a relation read. --}}
                {{ $book instanceof App\Lab\Models\LabBook
                    ? $book->author_names
                    : $book->authors->map(fn ($author) => $author->first_name.' '.$author->last_name)->join(', ') }}
            </p>
            <p class="text-xs text-stone-400">{{ $book->genre?->name ?? '— no genre —' }} · {{ $reviewCount }} reviews</p>
        </div>
    </div>

    @if ($feed)
        <ul class="mt-2 space-y-1 border-t border-stone-100 pt-2 text-xs">
            @forelse ($book->{$feed}->sortByDesc('id')->take(3) as $review)
                <li class="flex items-center gap-2">
                    <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-stone-200 text-[9px]"
                          title="{{ $review->user->profile?->avatar_path ?? 'no avatar' }}">{{ Str::substr($review->user->name, 0, 1) }}</span>
                    <span class="truncate">{{ $review->user->name }} — {{ $review->rating }}★
                        @if ($showReviewBook)
                            <span class="text-stone-400">on “{{ Str::limit($review->book->title, 20) }}”</span>
                        @endif
                    </span>
                </li>
            @empty
                <li class="text-stone-400">No reviews.</li>
            @endforelse
        </ul>
    @endif
</article>
