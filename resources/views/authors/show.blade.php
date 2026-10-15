<x-layouts.app :title="$author->first_name.' '.$author->last_name">
    <div class="grid gap-8 md:grid-cols-[12rem_1fr]">
        <div>
            <div class="flex h-48 items-center justify-center rounded-lg bg-stone-200 text-4xl font-semibold text-stone-500"
                 title="{{ $author->photo?->path ?? __('ui.authors.no_photo') }}">
                {{ Str::substr($author->first_name, 0, 1) }}{{ Str::substr($author->last_name, 0, 1) }}
            </div>
            <dl class="mt-3 space-y-1 text-sm text-stone-600">
                @if ($author->birth_date)
                    <div>{{ __('ui.authors.born', ['year' => $author->birth_date->format('Y')]) }}</div>
                @endif
                @if ($author->country)
                    <div>{{ __('ui.authors.country', ['country' => $author->country]) }}</div>
                @endif
                <div>{{ $author->photo ? __('ui.authors.has_photo') : __('ui.authors.no_photo') }}</div>
            </dl>
        </div>

        <div class="space-y-6">
            @forelse ($booksByRole as $role => $books)
                <x-card>
                    <x-slot:header>{{ App\Enums\AuthorRole::from($role)->label() }} ({{ $books->count() }})</x-slot:header>
                    <ol class="list-inside list-decimal space-y-1 text-sm">
                        @foreach ($books as $book)
                            {{-- Scoped route: the book is resolved through this author's books. --}}
                            <li><a href="{{ route('authors.books.show', [$author, $book]) }}" class="hover:text-amber-700">{{ $book->title }}</a>
                                <span class="text-stone-400">{{ $book->published_year }}</span></li>
                        @endforeach
                    </ol>
                </x-card>
            @empty
                <p class="text-stone-500">{{ __('ui.authors.no_books') }}</p>
            @endforelse
        </div>
    </div>
</x-layouts.app>
