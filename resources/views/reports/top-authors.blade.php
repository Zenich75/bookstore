<x-layouts.app :title="__('reports.top_authors.title')">
    @include('reports._nav')

    <p class="mb-4 text-sm text-stone-500">{{ __('reports.top_authors.intro', ['min' => $minReviews]) }}</p>

    <x-card>
        <table class="w-full text-sm">
            <thead class="text-left text-xs uppercase text-stone-400">
                <tr>
                    <th class="py-1">{{ __('reports.top_authors.columns.rank') }}</th>
                    <th class="py-1">{{ __('reports.top_authors.columns.author') }}</th>
                    <th class="py-1">{{ __('reports.top_authors.columns.rating') }}</th>
                    <th class="py-1 text-right">{{ __('reports.top_authors.columns.reviews') }}</th>
                    <th class="py-1 text-right">{{ __('reports.top_authors.columns.books') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($authors as $author)
                    <tr class="border-t border-stone-100">
                        <td class="py-1.5 text-stone-400">{{ $loop->iteration }}</td>
                        <td class="py-1.5"><a href="{{ route('authors.show', $author->slug) }}" class="hover:text-amber-700">{{ $author->first_name }} {{ $author->last_name }}</a></td>
                        <td class="py-1.5"><x-rating-stars :rating="$author->avg_rating" /></td>
                        <td class="py-1.5 text-right tabular-nums">{{ Number::format($author->reviews_count) }}</td>
                        <td class="py-1.5 text-right tabular-nums">{{ $author->books_count }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-4 text-center text-stone-500">{{ __('reports.top_authors.empty', ['min' => $minReviews]) }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-card>
</x-layouts.app>
