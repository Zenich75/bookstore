<x-layouts.app :title="__('ui.admin.title')">
    <div class="mb-6 grid gap-4 md:grid-cols-3">
        <x-card>
            <x-slot:header>{{ __('ui.admin.books_title') }}</x-slot:header>
            <a href="{{ route('admin.books.index') }}" class="text-amber-700 hover:underline">{{ __('ui.admin.all_books') }}</a> ·
            <a href="{{ route('admin.books.create') }}" class="text-amber-700 hover:underline">{{ __('ui.admin.new_book') }}</a>
        </x-card>
        <x-card>
            <x-slot:header>{{ __('ui.admin.reviews_title') }}</x-slot:header>
            <a href="{{ route('admin.reviews.index') }}" class="text-amber-700 hover:underline">{{ trans_choice('ui.admin.pending_count', $pendingReviews, ['count' => Number::format($pendingReviews)]) }}</a>
        </x-card>
        <x-card>
            <x-slot:header>{{ __('ui.nav.manage') }}</x-slot:header>
            <a href="{{ route('manage.warehouses.index') }}" class="text-amber-700 hover:underline">{{ __('ui.warehouses.title') }}</a>
        </x-card>
    </div>

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
        <x-slot:footer><a href="{{ route('admin.books.index', ['trashed' => 'only']) }}" class="text-amber-700 hover:underline">{{ __('ui.admin.trash') }}</a></x-slot:footer>
    </x-card>
</x-layouts.app>
