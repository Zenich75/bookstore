<x-layouts.app title="Admin">
    <p class="mb-4 rounded border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-900">
        Placeholder. Authentication and the admin-only middleware are added in stage 7 — for now this area is public.
    </p>

    <x-card>
        <x-slot:header>Recently deleted books</x-slot:header>
        <ul class="space-y-1 text-sm">
            @forelse ($trashedBooks as $book)
                <li><a href="{{ route('admin.books.show', $book) }}" class="hover:text-amber-700">{{ $book->title }}</a>
                    <span class="text-stone-400">deleted {{ $book->deleted_at->diffForHumans() }}</span></li>
            @empty
                <li class="text-stone-500">No soft-deleted books.</li>
            @endforelse
        </ul>
    </x-card>
</x-layouts.app>
