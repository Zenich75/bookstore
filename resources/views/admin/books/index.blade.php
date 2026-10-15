<x-layouts.app :title="__('ui.admin.books_title')">
    <div class="mb-4 flex flex-wrap items-center gap-3 text-sm">
        <form method="GET" class="flex gap-2">
            @if ($trashed)<input type="hidden" name="trashed" value="{{ $trashed }}">@endif
            <input type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('ui.admin.search') }}" class="form-input w-64">
            <button type="submit" class="btn-secondary">{{ __('ui.catalog.apply') }}</button>
        </form>
        @foreach (['' => __('ui.admin.filter_active'), 'with' => __('ui.admin.filter_with_trashed'), 'only' => __('ui.admin.filter_only_trashed')] as $value => $label)
            <a href="{{ route('admin.books.index', array_filter(['trashed' => $value, 'q' => request('q')])) }}"
               @class(['rounded-full border px-3 py-0.5', 'border-amber-500 bg-amber-50 text-amber-800' => ($trashed ?? '') === $value, 'border-stone-300' => ($trashed ?? '') !== $value])>{{ $label }}</a>
        @endforeach
        @can('manage', App\Models\Book::class)
            <a href="{{ route('admin.books.create') }}" class="btn ml-auto">{{ __('ui.admin.new_book') }}</a>
        @endcan
    </div>

    <x-card>
        <table class="w-full text-sm">
            <thead class="text-left text-stone-500">
                <tr>
                    <th class="py-2">{{ __('ui.admin.fields.id') }}</th>
                    <th class="py-2">{{ __('ui.admin.fields.title') }}</th>
                    <th class="py-2">{{ __('ui.admin.fields.isbn') }}</th>
                    <th class="py-2">{{ __('ui.admin.fields.genre') }}</th>
                    <th class="py-2 text-right">{{ __('ui.book.price') }}</th>
                    <th class="py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($books as $book)
                    <tr @class(['border-t border-stone-100', 'text-stone-400' => $book->trashed()])>
                        <td class="py-1.5">{{ $book->id }}</td>
                        <td class="py-1.5"><a href="{{ route('admin.books.show', $book) }}" class="hover:text-amber-700">{{ $book->title }}</a>
                            @if ($book->trashed())<span class="ml-1 rounded bg-red-50 px-1.5 text-xs text-red-700">{{ __('ui.admin.trashed_badge') }}</span>@endif</td>
                        <td class="py-1.5 tabular-nums">{{ $book->isbn_formatted }}</td>
                        <td class="py-1.5">{{ $book->genre->name }}</td>
                        <td class="py-1.5 text-right tabular-nums">{{ $book->price->format() }}</td>
                        <td class="py-1.5 text-right">@include('admin.books._actions')</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-4 text-center text-stone-500">{{ __('ui.catalog.empty') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-card>

    <div class="mt-6">{{ $books->links() }}</div>
</x-layouts.app>
