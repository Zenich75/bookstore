<x-layouts.app :title="$book->title">
    {{-- Genre breadcrumb: parent → genre (genre.parent eager-loaded). --}}
    <nav class="-mt-4 mb-6 text-sm text-stone-500">
        <a href="{{ route('books.index') }}" class="hover:underline">Catalog</a>
        @if ($book->genre->parent)
            / <a href="{{ route('genres.show', $book->genre->parent) }}" class="hover:underline">{{ $book->genre->parent->name }}</a>
        @endif
        / <a href="{{ route('genres.show', $book->genre) }}" class="hover:underline">{{ $book->genre->name }}</a>
        @isset($viaAuthor)
            <span class="ml-2 rounded bg-stone-200 px-2 py-0.5 text-xs">
                via <a href="{{ route('authors.show', $viaAuthor) }}" class="underline">{{ $viaAuthor->first_name }} {{ $viaAuthor->last_name }}</a>
            </span>
        @endisset
    </nav>

    <div class="grid gap-8 md:grid-cols-[16rem_1fr]">
        <div class="space-y-4">
            <div class="flex h-80 items-center justify-center rounded-lg bg-gradient-to-br from-amber-100 to-stone-200 p-4 text-center text-lg font-semibold text-stone-600"
                 title="{{ $book->cover?->path ?? 'No cover' }}">
                {{ $book->title }}
            </div>
            <x-card>
                <dl class="space-y-1 text-sm">
                    <div class="flex justify-between"><dt class="text-stone-500">Price</dt><dd class="font-semibold">{{ Number::currency($book->price / 100, 'USD') }}</dd></div>
                    <div class="flex justify-between"><dt class="text-stone-500">Format</dt><dd>{{ ucfirst($book->format->value) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-stone-500">ISBN</dt><dd>{{ $book->isbn }}</dd></div>
                    <div class="flex justify-between"><dt class="text-stone-500">Published</dt><dd>{{ $book->published_at?->format('M j, Y') ?? 'Unpublished' }}</dd></div>
                    @isset($book->meta['pages'])
                        <div class="flex justify-between"><dt class="text-stone-500">Pages</dt><dd>{{ $book->meta['pages'] }}</dd></div>
                    @endisset
                </dl>
            </x-card>
        </div>

        <div class="space-y-6">
            <section>
                <p class="text-stone-600">
                    {{-- $loop: first / last / iteration in a list of credits. --}}
                    @foreach ($book->authors as $author)
                        @if (! $loop->first){{ $loop->last ? ' and ' : ', ' }}@endif<a href="{{ route('authors.show', $author) }}" class="font-medium text-amber-800 hover:underline">{{ $author->first_name }} {{ $author->last_name }}</a>
                        <span class="text-xs text-stone-400">({{ str_replace('_', '-', $author->credit->role) }})</span>
                    @endforeach
                </p>
                <div class="mt-2"><x-rating-stars :rating="$book->approved_reviews_avg_rating" /> <span class="text-sm text-stone-500">· {{ $book->approved_reviews_count }} approved reviews</span></div>
            </section>

            @if ($book->genres->isNotEmpty() || $book->tags->isNotEmpty())
                <section class="flex flex-wrap gap-2 text-sm">
                    @foreach ($book->genres as $genre)
                        <a href="{{ route('genres.show', $genre) }}" class="rounded-full border border-stone-300 px-3 py-0.5 hover:bg-stone-100">{{ $genre->name }}</a>
                    @endforeach
                    @foreach ($book->tags as $tag)
                        <span class="rounded-full bg-stone-200 px-3 py-0.5 text-stone-600">#{{ $tag->name }}</span>
                    @endforeach
                </section>
            @endif

            <x-card>
                <x-slot:header>Stock by warehouse</x-slot:header>
                <table class="w-full text-sm">
                    <tbody>
                        @forelse ($book->warehouses as $warehouse)
                            <tr class="border-b border-stone-100 last:border-0">
                                <td class="py-1.5"><a href="{{ route('warehouses.show', $warehouse) }}" class="hover:underline">{{ $warehouse->name }}</a></td>
                                <td class="py-1.5 text-stone-500">{{ $warehouse->stock->shelf_code }}</td>
                                <td class="py-1.5 text-right">
                                    <x-stock-badge :quantity="$warehouse->stock->quantity" :reserved="$warehouse->stock->reserved" />
                                </td>
                            </tr>
                        @empty
                            <tr><td class="py-1.5 text-stone-500">Not stocked in any warehouse.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-card>

            <div class="grid gap-4 md:grid-cols-2">
                @foreach (['Latest review' => $book->latestReview, 'Best review' => $book->bestReview] as $heading => $review)
                    <x-card>
                        <x-slot:header>{{ $heading }}</x-slot:header>
                        @if ($review)
                            <x-rating-stars :rating="$review->rating" />
                            <p class="mt-2 text-sm">{{ Str::limit($review->body, 160) }}</p>
                            <x-slot:footer>{{ $review->user?->name ?? 'Deleted user' }} · {{ $review->created_at->diffForHumans() }}</x-slot:footer>
                        @else
                            <p class="text-sm text-stone-500">No reviews yet.</p>
                        @endif
                    </x-card>
                @endforeach
            </div>

            <section>
                <h2 class="mb-3 text-lg font-semibold">Reviews ({{ $reviews->total() }})</h2>
                <div class="space-y-3">
                    @forelse ($reviews as $review)
                        <x-card>
                            <div class="flex items-center justify-between">
                                <span class="font-medium">{{ $review->user->name }}</span>
                                <x-rating-stars :rating="$review->rating" />
                            </div>
                            {{-- {{ }} escapes HTML: review bodies are user input. --}}
                            <p class="mt-2 text-sm text-stone-700">{{ $review->body }}</p>
                        </x-card>
                    @empty
                        <p class="text-sm text-stone-500">No approved reviews yet.</p>
                    @endforelse
                </div>
                <div class="mt-4">{{ $reviews->links() }}</div>
            </section>
        </div>
    </div>
</x-layouts.app>
