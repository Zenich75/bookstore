<?php

use App\Models\Author;
use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\Scopes\ApprovedScope;
use App\Models\Tag;
use App\Models\Warehouse;

beforeEach(function () {
    $this->genre = Genre::factory()->create();
    [$this->first, $this->second] = Author::factory()->count(2)->create();
    $this->tags = Tag::factory()->count(2)->create();
    $this->extraGenre = Genre::factory()->create();
});

function bookPayload(array $overrides = []): array
{
    $test = test();

    return [
        'title' => 'Лісова пісня',
        'isbn' => '978-617-0000-01-7',
        'price' => '249,50',
        'format' => 'hardcover',
        'genre_id' => $test->genre->id,
        'published_at' => '2011-01-01',
        'authors' => [
            ['id' => $test->second->id, 'role' => 'author'],
            ['id' => $test->first->id, 'role' => 'translator'],
            ['id' => '', 'role' => 'author'], // empty row: ignored
        ],
        'genres' => [$test->extraGenre->id, $test->genre->id], // main genre is dropped from secondary
        'tags' => $test->tags->modelKeys(),
        ...$overrides,
    ];
}

test('readers and managers cannot reach the admin book pages', function () {
    $book = Book::factory()->create();

    asReader();
    $this->get(route('admin.books.index'))->assertForbidden();
    asManager();
    $this->get(route('admin.books.create'))->assertForbidden();
    $this->post(route('admin.books.store'), bookPayload())->assertForbidden();
    $this->delete(route('admin.books.destroy', $book))->assertForbidden();

    expect(Book::count())->toBe(1);
});

test('store: book with Money price, ISBN digits, slug, authors with pivot roles, genres and tags', function () {
    asAdmin();

    $this->post(route('admin.books.store'), bookPayload())->assertRedirect()->assertSessionHas('status');

    $book = Book::with(['authors', 'genres', 'tags'])->sole();

    expect($book->price->cents)->toBe(24950)
        ->and($book->isbn)->toBe('9786170000017')
        ->and($book->slug)->toBe('lisova-pisnia')
        ->and($book->published_year)->toBe(2011)
        ->and($book->authors->map(fn (Author $a) => [$a->id, $a->credit->role, $a->credit->sort_order])->all())
        ->toBe([[$this->second->id, 'author', 0], [$this->first->id, 'translator', 1]])
        ->and($book->genres->modelKeys())->toBe([$this->extraGenre->id])
        ->and($book->tags->modelKeys())->toEqualCanonicalizing($this->tags->modelKeys());
});

test('validation: duplicate ISBN (with dashes), bad price, unknown author, date outside TIMESTAMP range', function () {
    asAdmin();
    Book::factory()->create(['isbn' => '9786170000017']);

    $this->post(route('admin.books.store'), bookPayload([
        'price' => '12.345', 'published_at' => '1911-01-01', 'authors' => [['id' => 999999, 'role' => 'author']],
    ]))->assertSessionHasErrors(['isbn', 'price', 'published_at', 'authors.0.id']);
});

test('update: sync changes pivot roles/order and detaches the rest; the slug stays', function () {
    asAdmin();
    $this->post(route('admin.books.store'), bookPayload());
    $book = Book::sole();

    $this->put(route('admin.books.update', $book), bookPayload([
        'title' => 'Лісова пісня (нове видання)',
        'authors' => [['id' => $this->first->id, 'role' => 'co_author']],
        'genres' => [],
        'tags' => [$this->tags[0]->id],
    ]))->assertRedirect(route('admin.books.show', $book));

    $book = $book->fresh(['authors', 'genres', 'tags']);

    expect($book->title)->toBe('Лісова пісня (нове видання)')
        ->and($book->slug)->toBe('lisova-pisnia')
        ->and($book->authors->map(fn (Author $a) => [$a->id, $a->credit->role, $a->credit->sort_order])->all())->toBe([[$this->first->id, 'co_author', 0]])
        ->and($book->genres)->toBeEmpty()
        ->and($book->tags->modelKeys())->toBe([$this->tags[0]->id]);
});

test('soft delete, trashed filter, restore, force delete', function () {
    asAdmin();
    $book = Book::factory()->create(['title' => 'Кобзар']);
    $book->tags()->attach($this->tags[0]);
    Warehouse::factory()->create()->books()->attach($book, ['quantity' => 3]);

    $this->delete(route('admin.books.destroy', $book))->assertRedirect(route('admin.books.index'));
    expect($book->fresh()->trashed())->toBeTrue();

    $link = 'href="'.route('admin.books.show', $book).'"';
    $this->get(route('admin.books.index'))->assertDontSee($link, false);
    $this->get(route('admin.books.index', ['trashed' => 'only']))->assertSee($link, false);
    $this->get(route('books.show', $book))->assertNotFound();

    $this->patch(route('admin.books.restore', $book))->assertRedirect(route('admin.books.show', $book));
    expect($book->fresh()->trashed())->toBeFalse();

    // forceDelete is only offered (and allowed) for trashed books.
    $this->delete(route('admin.books.force-delete', $book))->assertForbidden();

    $book->delete();
    $this->delete(route('admin.books.force-delete', $book))->assertRedirect();

    expect(Book::withTrashed()->find($book->id))->toBeNull()
        ->and(DB::table('taggables')->where('taggable_id', $book->id)->count())->toBe(0)
        ->and(DB::table('stocks')->where('book_id', $book->id)->count())->toBe(0);
});

test('review moderation: pending list, approve, reject', function () {
    asAdmin();
    $pending = Review::factory()->count(2)->create(['body' => 'Чекає на модерацію']);
    Review::factory()->approved()->create(['body' => 'Вже схвалений']);

    $this->get(route('admin.reviews.index'))->assertOk()->assertSee('Чекає на модерацію')->assertDontSee('Вже схвалений');

    $this->patch(route('admin.reviews.approve', $pending[0]))->assertSessionHas('status');
    $this->delete(route('admin.reviews.destroy', $pending[1]))->assertSessionHas('status');

    expect($pending[0]->fresh()->is_approved)->toBeTrue()
        ->and(Review::withoutGlobalScope(ApprovedScope::class)->find($pending[1]->id))->toBeNull();

    // Approving twice: a state rule, excluded from the admin bypass in Gate::before.
    $this->patch(route('admin.reviews.approve', $pending[0]))->assertForbidden();
});

test('a manager cannot moderate reviews', function () {
    asManager();
    $review = Review::factory()->create();

    $this->patch(route('admin.reviews.approve', $review))->assertForbidden();
    expect($review->fresh()->is_approved)->toBeFalse();
});
