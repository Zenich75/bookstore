<li class="flex items-center justify-between py-2">
    <a href="{{ route('authors.show', $author) }}" class="hover:text-amber-700">{{ $author->last_name }}, {{ $author->first_name }}</a>
    <span class="text-sm text-stone-500">{{ $author->country }} · {{ trans_choice('ui.counts.books', $author->books_count) }}</span>
</li>
