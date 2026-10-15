<x-layouts.app title="Authors">
    <x-card>
        <ul class="divide-y divide-stone-100">
            {{-- @each renders a partial per item (with an "empty" view); unlike @include,
                 the partial does NOT see this view's variables — only $author. --}}
            @each('authors.partials.row', $authors, 'author', 'authors.partials.empty')
        </ul>
    </x-card>

    <div class="mt-6">{{ $authors->links() }}</div>
</x-layouts.app>
