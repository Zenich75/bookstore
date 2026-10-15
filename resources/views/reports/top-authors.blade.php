<x-layouts.app title="Top authors">
    @include('reports._nav')

    <p class="mb-4 text-sm text-stone-500">Average rating of approved reviews of their books (as author or co-author); at least {{ $minReviews }} reviews. "Books" = rated books.</p>

    <x-card>
        <table class="w-full text-sm">
            <thead class="text-left text-xs uppercase text-stone-400">
                <tr>
                    <th class="py-1">#</th>
                    <th class="py-1">Author</th>
                    <th class="py-1">Rating</th>
                    <th class="py-1 text-right">Reviews</th>
                    <th class="py-1 text-right">Books</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($authors as $author)
                    <tr class="border-t border-stone-100">
                        <td class="py-1.5 text-stone-400">{{ $loop->iteration }}</td>
                        <td class="py-1.5"><a href="{{ route('authors.show', $author->slug) }}" class="hover:text-amber-700">{{ $author->first_name }} {{ $author->last_name }}</a></td>
                        <td class="py-1.5"><x-rating-stars :rating="$author->avg_rating" /></td>
                        <td class="py-1.5 text-right tabular-nums">{{ number_format($author->reviews_count) }}</td>
                        <td class="py-1.5 text-right tabular-nums">{{ $author->books_count }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-4 text-center text-stone-500">No author has {{ $minReviews }}+ approved reviews yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-card>
</x-layouts.app>
