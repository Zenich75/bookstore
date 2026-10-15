{{-- One form for create and edit: $book->exists decides the action and method. --}}
@php
    $credits = old('authors', $book->exists
        ? $book->authors->map(fn ($author) => ['id' => $author->id, 'role' => $author->credit->role])->all()
        : []);
    // Always offer a few empty rows for new credits.
    $credits = array_pad(array_values($credits), max(3, count($credits) + 2), ['id' => null, 'role' => App\Enums\AuthorRole::Author->value]);
    $selectedGenres = array_map('intval', old('genres', $book->exists ? $book->genres->modelKeys() : []));
    $selectedTags = array_map('intval', old('tags', $book->exists ? $book->tags->modelKeys() : []));
@endphp

<x-layouts.app :title="$book->exists ? __('ui.admin.edit_book', ['title' => $book->title]) : __('ui.admin.new_book')">
    @if ($errors->any())
        <div class="mb-4 rounded border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-800">
            <ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ $book->exists ? route('admin.books.update', $book) : route('admin.books.store') }}" class="grid gap-6 lg:grid-cols-2">
        @csrf
        @if ($book->exists) @method('PUT') @endif

        <x-card>
            <x-slot:header>{{ __('ui.admin.section_main') }}</x-slot:header>
            <div class="space-y-4">
                <x-field :label="__('ui.admin.fields.title')" name="title">
                    <input type="text" name="title" value="{{ old('title', $book->title) }}" required maxlength="255" class="form-input">
                </x-field>
                <div class="grid grid-cols-2 gap-4">
                    <x-field :label="__('ui.admin.fields.isbn')" name="isbn">
                        <input type="text" name="isbn" value="{{ old('isbn', $book->isbn_formatted) }}" required placeholder="978-617-…" class="form-input">
                    </x-field>
                    <x-field :label="__('ui.admin.fields.price_uah')" name="price">
                        <input type="text" name="price" value="{{ old('price', $book->price?->toUah()) }}" required inputmode="decimal" placeholder="249.50" class="form-input">
                    </x-field>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <x-field :label="__('ui.book.format')" name="format">
                        <select name="format" class="form-input">
                            @foreach ($formats as $format)
                                <option value="{{ $format->value }}" @selected(old('format', $book->format?->value) === $format->value)>{{ $format->label() }}</option>
                            @endforeach
                        </select>
                    </x-field>
                    <x-field :label="__('ui.book.published')" name="published_at">
                        <input type="date" name="published_at" value="{{ old('published_at', $book->published_at?->format('Y-m-d')) }}" class="form-input">
                    </x-field>
                </div>
                <x-field :label="__('ui.admin.fields.main_genre')" name="genre_id">
                    <select name="genre_id" class="form-input" required>
                        <option value="">—</option>
                        @foreach ($genres as $genre)
                            <option value="{{ $genre->id }}" @selected((int) old('genre_id', $book->genre_id) === $genre->id)>{{ $genre->name }}</option>
                        @endforeach
                    </select>
                </x-field>
            </div>
        </x-card>

        <x-card>
            {{-- Saved with $book->authors()->sync([id => ['role' => ..., 'sort_order' => row]]). --}}
            <x-slot:header>{{ __('ui.admin.fields.authors') }}</x-slot:header>
            <div class="space-y-2">
                @foreach ($credits as $i => $credit)
                    <div class="grid grid-cols-[1fr_11rem] gap-2">
                        <select name="authors[{{ $i }}][id]" class="form-input" aria-label="{{ __('ui.admin.fields.author') }} {{ $i + 1 }}">
                            <option value="">—</option>
                            @foreach ($authors as $author)
                                <option value="{{ $author->id }}" @selected((int) ($credit['id'] ?? 0) === $author->id)>{{ $author->last_name }}, {{ $author->first_name }}</option>
                            @endforeach
                        </select>
                        <select name="authors[{{ $i }}][role]" class="form-input" aria-label="{{ __('ui.admin.fields.role') }} {{ $i + 1 }}">
                            @foreach ($roles as $role)
                                <option value="{{ $role->value }}" @selected(($credit['role'] ?? null) === $role->value)>{{ $role->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                @endforeach
                <p class="text-xs text-stone-500">{{ __('ui.admin.authors_hint') }}</p>
            </div>
        </x-card>

        <x-card>
            <x-slot:header>{{ __('ui.admin.fields.secondary_genres') }}</x-slot:header>
            <div class="grid grid-cols-2 gap-1 text-sm">
                @foreach ($genres as $genre)
                    <label class="flex items-center gap-2"><input type="checkbox" name="genres[]" value="{{ $genre->id }}" @checked(in_array($genre->id, $selectedGenres, true))> {{ $genre->name }}</label>
                @endforeach
            </div>
        </x-card>

        <x-card>
            <x-slot:header>{{ __('ui.admin.fields.tags') }}</x-slot:header>
            <div class="grid grid-cols-2 gap-1 text-sm">
                @foreach ($tags as $tag)
                    <label class="flex items-center gap-2"><input type="checkbox" name="tags[]" value="{{ $tag->id }}" @checked(in_array($tag->id, $selectedTags, true))> {{ $tag->name }}</label>
                @endforeach
            </div>
        </x-card>

        <div class="lg:col-span-2">
            <button type="submit" class="btn">{{ __('ui.manage.save') }}</button>
            <a href="{{ $book->exists ? route('admin.books.show', $book) : route('admin.books.index') }}" class="ml-3 text-sm text-stone-500 hover:underline">{{ __('ui.admin.cancel') }}</a>
        </div>
    </form>
</x-layouts.app>
