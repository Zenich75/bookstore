<x-layouts.app :title="__('ui.admin.title')">
    <p class="mb-4 rounded border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-900">
        {{ __('ui.admin.placeholder') }}
    </p>

    <x-card>
        <x-slot:header>{{ __('ui.admin.recently_deleted') }}</x-slot:header>
        <ul class="space-y-1 text-sm">
            @forelse ($trashedBooks as $book)
                <li><a href="{{ route('admin.books.show', $book) }}" class="hover:text-amber-700">{{ $book->title }}</a>
                    <span class="text-stone-400">{{ __('ui.admin.deleted_ago', ['time' => $book->deleted_at->diffForHumans()]) }}</span></li>
            @empty
                <li class="text-stone-500">{{ __('ui.admin.no_trashed') }}</li>
            @endforelse
        </ul>
    </x-card>
</x-layouts.app>
