<?php

use App\Models\Author;
use App\Models\Book;
use App\Models\Genre;

beforeEach(function () {
    app()->setLocale('uk');
    asAdmin();
    $this->genre = Genre::factory()->create();
    [$this->first, $this->second] = Author::factory()->count(2)->create();
});

/**
 * A valid StoreBookRequest payload.
 */
function bookForm(array $overrides = []): array
{
    return [
        'title' => 'Захар Беркут',
        'isbn' => '978-617-0000-01-9',
        'price' => '199,90',
        'format' => 'paperback',
        'genre_id' => test()->genre->id,
        'authors' => [['id' => test()->first->id, 'role' => 'author']],
        ...$overrides,
    ];
}

test('ISBN: digits are normalized, the checksum is verified', function (string $isbn, bool $valid) {
    $response = $this->post(route('admin.books.store'), bookForm(['isbn' => $isbn]));

    $valid ? $response->assertSessionDoesntHaveErrors('isbn') : $response->assertSessionHasErrors('isbn');
})->with([
    'dashes' => ['978-617-0000-01-9', true],
    'spaces' => ['978 617 0000 019', true],
    'plain digits' => ['9780306406157', true],
    'wrong check digit' => ['978-617-0000-01-7', false],
    'too short' => ['978-617-0000', false],
    'ISBN-10' => ['0-306-40615-2', false],
    'letters only' => ['abc', false],
]);

test('ISBN errors are in Ukrainian, with the attribute name', function () {
    $this->post(route('admin.books.store'), bookForm(['isbn' => '978-617-0000-01-7']))
        ->assertSessionHasErrors(['isbn' => __('validation.isbn13_checksum', ['attribute' => 'ISBN'])]);

    expect(session('errors')->first('isbn'))->toContain('контрольною цифрою');
});

test('price: comma or dot, thousands spaces; stored as kopecks without floats', function (string $input, int $cents) {
    $this->post(route('admin.books.store'), bookForm(['price' => $input]))->assertSessionHasNoErrors();

    expect(Book::sole()->price->cents)->toBe($cents);
})->with([
    ['199,90', 19990],
    ['199.90', 19990],
    ['1 299,9', 129990],
    ['249', 24900],
    ['0,05', 5],
]);

test('price: invalid formats are rejected', function (string $input) {
    $this->post(route('admin.books.store'), bookForm(['price' => $input]))->assertSessionHasErrors('price');
})->with(['12.345', '-5', 'abc', '1e3', '199,90 грн', '']);

test('unique ISBN ignores the book being edited, not other books', function () {
    $book = Book::factory()->create(['isbn' => '9786170000019']);
    $other = Book::factory()->create();

    $this->put(route('admin.books.update', $book), bookForm())->assertSessionHasNoErrors();
    $this->put(route('admin.books.update', $book), bookForm(['isbn' => $other->isbn]))->assertSessionHasErrors('isbn');
});

test('a soft-deleted book still owns its ISBN and slug (the DB unique index covers trashed rows)', function () {
    Book::factory()->create(['isbn' => '9786170000019', 'slug' => 'zakhar-berkut'])->delete();

    $this->post(route('admin.books.store'), bookForm(['slug' => 'Захар Беркут']))
        ->assertSessionHasErrors(['isbn', 'slug']);

    expect(Book::withTrashed()->count())->toBe(1);
});

test('slug: optional, normalized to ASCII; blank on update keeps the current one', function () {
    $this->post(route('admin.books.store'), bookForm(['slug' => 'Моя Книга!']))->assertSessionHasNoErrors();
    $book = Book::sole();
    expect($book->slug)->toBe('moia-knyha');

    $this->put(route('admin.books.update', $book), bookForm(['title' => 'Нова назва', 'slug' => '']))->assertSessionHasNoErrors();
    expect($book->fresh()->slug)->toBe('moia-knyha');
});

test('authors array: distinct and existing ids, valid roles', function (Closure $authors, array $errors) {
    $response = $this->post(route('admin.books.store'), bookForm(['authors' => $authors()]));

    $errors ? $response->assertSessionHasErrors($errors) : $response->assertSessionHasNoErrors();
})->with([
    'two different authors' => [fn () => [['id' => test()->first->id, 'role' => 'author'], ['id' => test()->second->id, 'role' => 'translator']], []],
    'empty rows are skipped' => [fn () => [['id' => null, 'role' => 'author'], ['id' => test()->first->id, 'role' => 'author']], []],
    'the same author twice' => [fn () => [['id' => test()->first->id, 'role' => 'author'], ['id' => test()->first->id, 'role' => 'translator']], ['authors.0.id', 'authors.1.id']],
    'unknown author' => [fn () => [['id' => 999999, 'role' => 'author']], ['authors.0.id']],
    'unknown role' => [fn () => [['id' => test()->first->id, 'role' => 'editor']], ['authors.0.role']],
    'missing role' => [fn () => [['id' => test()->first->id]], ['authors.0.role']],
    'negative sort order' => [fn () => [['id' => test()->first->id, 'role' => 'author', 'sort_order' => -1]], ['authors.0.sort_order']],
]);

test('the distinct error uses the custom per-attribute message', function () {
    $this->post(route('admin.books.store'), bookForm(['authors' => [
        ['id' => $this->first->id, 'role' => 'author'], ['id' => $this->first->id, 'role' => 'author'],
    ]]))->assertSessionHasErrors(['authors.1.id' => trans('validation.custom')['authors.*.id']['distinct']]);
});

test('sort_order decides the author order; sparse row keys from the repeater are fine', function () {
    $this->post(route('admin.books.store'), bookForm(['authors' => [
        3 => ['id' => $this->first->id, 'role' => 'author', 'sort_order' => 2],
        7 => ['id' => $this->second->id, 'role' => 'co_author', 'sort_order' => 1],
    ]]))->assertSessionHasNoErrors();

    expect(Book::sole()->authors()->get()->map(fn (Author $a) => [$a->id, $a->credit->sort_order])->all())
        ->toBe([[$this->second->id, 0], [$this->first->id, 1]]);
});

test('extra input is not mass-assigned: only validated fields reach the model', function () {
    $this->post(route('admin.books.store'), bookForm(['rating_avg' => '5.00', 'deleted_at' => now()->toDateTimeString()]))
        ->assertSessionHasNoErrors();

    $book = Book::sole();
    expect($book->rating_avg)->toBeNull()->and($book->trashed())->toBeFalse();
});

/**
 * Invalid store request with a sparse repeater row key (5), as the JS repeater sends after removals.
 */
function invalidBookForm(): array
{
    return bookForm([
        'title' => '',
        'isbn' => '978-617-0000-01-7',
        'authors' => [5 => ['id' => test()->second->id, 'role' => 'editor']],
    ]);
}

test('a failed submit flashes errors and the raw old input (incl. authors.N.* keys)', function () {
    $this->from(route('admin.books.create'))
        ->post(route('admin.books.store'), invalidBookForm())
        ->assertRedirect(route('admin.books.create'))
        ->assertSessionHasErrors(['title', 'isbn', 'authors.5.role'])
        // What the user typed, not the digits prepareForValidation() made of it.
        ->assertSessionHasInput('isbn', '978-617-0000-01-7')
        ->assertSessionHasInput('authors.5.id', (string) $this->second->id);
});

test('the form is repopulated from old input and shows errors next to the fields', function () {
    // No session assertions on the POST: with JSON session serialization, reading the
    // session between two test requests drops the flashed error bag.
    $this->from(route('admin.books.create'))->post(route('admin.books.store'), invalidBookForm());

    $this->get(route('admin.books.create'))
        ->assertOk()
        ->assertSee('value="978-617-0000-01-7"', false)
        ->assertSee('name="authors[5][role]"', false)
        ->assertSee('id="error-authors-5-role"', false)
        ->assertSee('aria-invalid="true"', false)
        ->assertSee(__('validation.required', ['attribute' => __('validation.attributes.title')]));
});

test('the edit form shows the current credits and a template row for the repeater', function () {
    $book = Book::factory()->create();
    $book->authors()->attach($this->first, ['role' => 'translator', 'sort_order' => 0]);

    $this->get(route('admin.books.edit', $book))
        ->assertOk()
        ->assertSee('name="_method" value="PUT"', false)
        ->assertSee('name="authors[0][id]"', false)
        ->assertSee('<template data-row-template>', false)
        ->assertSee('name="authors[__INDEX__][role]"', false);
});
