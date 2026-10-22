<?php

use App\Models\Author;
use App\Models\Book;
use App\Models\Genre;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\ClassMorphViolationException;
use Illuminate\Support\Facades\DB;

test('enforced morph map stores aliases instead of class names', function () {
    $book = Book::factory()->create();
    $author = Author::factory()->create();
    $tag = Tag::factory()->create();

    $book->images()->create(['path' => 'covers/x.jpg', 'type' => 'cover']);
    $author->tags()->attach($tag);

    expect(DB::table('images')->value('imageable_type'))->toBe('book')
        ->and(DB::table('taggables')->value('taggable_type'))->toBe('author');
});

test('a model without an alias cannot be used polymorphically', function () {
    // User got an alias in stage 9 (notifications.notifiable_type); Genre has none.
    (new Genre)->getMorphClass();
})->throws(ClassMorphViolationException::class);
