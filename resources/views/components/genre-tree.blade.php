{{--
    Recursive anonymous component: renders itself for children.
    It only descends into relations that were eager-loaded (relationLoaded) —
    strict mode would throw on lazy loading.
--}}
@props(['genres', 'active' => null, 'counts' => false, 'link' => 'catalog'])

<ul {{ $attributes->class(['space-y-1']) }}>
    @foreach ($genres as $genre)
        <li>
            <a href="{{ $link === 'catalog' ? route('books.index', ['genre' => $genre->slug]) : route('genres.show', $genre) }}"
               @class([
                   'hover:text-amber-700',
                   'font-semibold text-amber-700' => $active?->is($genre),
               ])>{{ $genre->name }}</a>
            @if ($counts)
                <span class="text-xs text-stone-400">({{ $genre->books_count }})</span>
            @endif

            @if ($genre->relationLoaded('children') && $genre->children->isNotEmpty())
                <x-genre-tree :genres="$genre->children" :active="$active" :counts="$counts" :link="$link" class="ml-4 mt-1" />
            @endif
        </li>
    @endforeach
</ul>
