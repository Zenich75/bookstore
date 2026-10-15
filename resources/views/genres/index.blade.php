<x-layouts.app title="Genres">
    <p class="mb-4 text-sm text-stone-500">Counts are books with this main genre. Click a genre to open the filtered catalog.</p>
    <x-card>
        <x-genre-tree :genres="$genres" counts link="genre" class="columns-1 md:columns-2" />
    </x-card>
</x-layouts.app>
