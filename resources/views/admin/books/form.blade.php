{{-- One form for create and edit: $book->exists decides the action and method. --}}
@php
    // old('authors') keeps the submitted row keys (0, 3, 7 after rows were removed),
    // so each row's errors (authors.3.id) still land next to that row.
    $credits = old('authors', $book->exists
        ? $book->authors->map(fn ($author) => ['id' => $author->id, 'role' => $author->credit->role, 'sort_order' => $author->credit->sort_order])->all()
        : []);
    $credits = $credits ?: [['id' => null, 'role' => App\Enums\AuthorRole::Author->value, 'sort_order' => 0]];
    $authorOptions = $authors->mapWithKeys(fn ($author) => [$author->id => $author->last_name.', '.$author->first_name])->all();
    $selectedGenres = array_map('intval', old('genres', $book->exists ? $book->genres->modelKeys() : []));
    $selectedTags = array_map('intval', old('tags', $book->exists ? $book->tags->modelKeys() : []));
@endphp

<x-layouts.app :title="$book->exists ? __('ui.admin.edit_book', ['title' => $book->title]) : __('ui.admin.new_book')">
    @if ($errors->any())
        <div class="mb-4 rounded border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-800" role="alert">
            <ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ $book->exists ? route('admin.books.update', $book) : route('admin.books.store') }}" class="grid gap-6 lg:grid-cols-2" novalidate>
        {{-- CSRF token (419 without it) and method spoofing: HTML forms only send GET/POST. --}}
        @csrf
        @if ($book->exists) @method('PUT') @endif

        <x-card>
            <x-slot:header>{{ __('ui.admin.section_main') }}</x-slot:header>
            <div class="space-y-4">
                <x-form.input name="title" :label="__('ui.admin.fields.title')" :value="$book->title" required maxlength="255" />
                <x-form.input name="slug" :label="__('ui.admin.fields.slug')" :value="$book->slug" :hint="__('ui.admin.slug_hint')" maxlength="255" />
                <div class="grid grid-cols-2 gap-4">
                    <x-form.input name="isbn" :label="__('ui.admin.fields.isbn')" :value="$book->isbn_formatted" required placeholder="978-617-…" />
                    <x-form.input name="price" :label="__('ui.admin.fields.price_uah')" :value="$book->price?->toUah()" required inputmode="decimal" placeholder="249,50" />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <x-form.select name="format" :label="__('ui.book.format')" :options="$formats" :value="$book->format" />
                    <x-form.input type="date" name="published_at" :label="__('ui.book.published')" :value="$book->published_at?->format('Y-m-d')" />
                </div>
                <x-form.select name="genre_id" :label="__('ui.admin.fields.main_genre')" :options="$genres->pluck('name', 'id')" :value="$book->genre_id" placeholder="—" required />
            </div>
        </x-card>

        <x-card>
            {{-- Saved with $book->authors()->sync([id => ['role' => ..., 'sort_order' => ...]]). --}}
            <x-slot:header>{{ __('ui.admin.fields.authors') }}</x-slot:header>
            <div class="space-y-2" data-repeater>
                <div class="space-y-2" data-rows>
                    @foreach ($credits as $i => $credit)
                        @include('admin.books._author-row', ['index' => $i, 'credit' => $credit])
                    @endforeach
                </div>
                <x-form.error name="authors" />

                {{-- A blank row for the "add" button; __INDEX__ is replaced in JS. Inside <template>, the
                     fields are not part of the form, so this row is never submitted. --}}
                <template data-row-template>
                    @include('admin.books._author-row', ['index' => '__INDEX__', 'credit' => ['id' => null, 'role' => App\Enums\AuthorRole::Author->value, 'sort_order' => null]])
                </template>

                <button type="button" class="btn-secondary" data-add-row>{{ __('ui.admin.add_author') }}</button>
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
            <x-form.error name="genres" />
        </x-card>

        <x-card>
            <x-slot:header>{{ __('ui.admin.fields.tags') }}</x-slot:header>
            <div class="grid grid-cols-2 gap-1 text-sm">
                @foreach ($tags as $tag)
                    <label class="flex items-center gap-2"><input type="checkbox" name="tags[]" value="{{ $tag->id }}" @checked(in_array($tag->id, $selectedTags, true))> {{ $tag->name }}</label>
                @endforeach
            </div>
            <x-form.error name="tags" />
        </x-card>

        <div class="lg:col-span-2">
            <button type="submit" class="btn">{{ __('ui.manage.save') }}</button>
            <a href="{{ $book->exists ? route('admin.books.show', $book) : route('admin.books.index') }}" class="ml-3 text-sm text-stone-500 hover:underline">{{ __('ui.admin.cancel') }}</a>
        </div>
    </form>

    @push('scripts')
        <script>
            // Authors repeater, no framework: clone the <template> row with a new index;
            // remove a row by removing its element. Indices only need to be unique.
            document.querySelectorAll('[data-repeater]').forEach((repeater) => {
                const rows = repeater.querySelector('[data-rows]');
                const template = repeater.querySelector('[data-row-template]');
                let next = Date.now();

                repeater.querySelector('[data-add-row]').addEventListener('click', () => {
                    const html = template.innerHTML.replaceAll('__INDEX__', String(next++));
                    rows.insertAdjacentHTML('beforeend', html);
                    rows.lastElementChild.querySelector('select')?.focus();
                });

                rows.addEventListener('click', (event) => {
                    const button = event.target.closest('[data-remove-row]');
                    if (button) {
                        button.closest('[data-row]').remove();
                    }
                });
            });
        </script>
    @endpush
</x-layouts.app>
