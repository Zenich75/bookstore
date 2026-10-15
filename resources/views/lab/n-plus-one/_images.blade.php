{{-- Images feed (variant j): owners resolved through morphTo. --}}
<ul class="grid gap-1 text-xs md:grid-cols-2">
    @foreach ($images as $image)
        <li class="truncate rounded border border-stone-200 bg-white px-2 py-1">
            <span class="text-stone-400">{{ $image->type->value }}</span>
            @if ($image->imageable instanceof App\Models\Book)
                {{ $image->imageable->title }} <span class="text-stone-400">· {{ $image->imageable->genre->name }}</span>
            @else
                {{ $image->imageable->first_name }} {{ $image->imageable->last_name }}
                <span class="text-stone-400">· {{ $image->imageable->books->count() }} books</span>
            @endif
        </li>
    @endforeach
</ul>
