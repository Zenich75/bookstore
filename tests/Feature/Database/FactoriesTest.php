<?php

use App\Enums\BookFormat;
use App\Enums\ImageType;
use App\Enums\StockMovementReason;
use App\Enums\UserRole;
use App\Models\Author;
use App\Models\Book;
use App\Models\Genre;
use App\Models\Image;
use App\Models\Profile;
use App\Models\Review;
use App\Models\StockMovement;
use App\Models\Tag;
use App\Models\User;
use App\Models\Warehouse;

test('each model factory creates a persisted model', function (string $model) {
    $instance = $model::factory()->create();

    expect($instance->exists)->toBeTrue()
        ->and($model::query()->whereKey($instance->getKey())->exists())->toBeTrue();
})->with([
    User::class, Profile::class, Warehouse::class, Author::class, Genre::class,
    Book::class, Review::class, Image::class, Tag::class, StockMovement::class,
]);

test('user factory states set role and warehouse', function () {
    $reader = User::factory()->create();
    $manager = User::factory()->manager()->create();
    $admin = User::factory()->admin()->create();

    expect($reader->role)->toBe(UserRole::Reader)
        ->and($reader->warehouse_id)->toBeNull()
        ->and($manager->role)->toBe(UserRole::Manager)
        ->and(Warehouse::find($manager->warehouse_id))->not->toBeNull()
        ->and($admin->role)->toBe(UserRole::Admin);
});

test('book factory states', function () {
    $published = Book::factory()->published()->create();
    $unpublished = Book::factory()->unpublished()->create();
    $ebook = Book::factory()->format(BookFormat::Ebook)->create();

    expect($published->published_at)->not->toBeNull()
        ->and($published->published_at->isPast())->toBeTrue()
        ->and($published->published_year)->toBe($published->published_at->year)
        ->and($unpublished->published_at)->toBeNull()
        ->and($unpublished->published_year)->toBeNull()
        ->and($ebook->format)->toBe(BookFormat::Ebook);
});

test('book factory produces valid unique isbn-13, cents price and array meta', function () {
    $books = Book::factory()->count(50)->make(['genre_id' => 1]);

    expect($books->pluck('isbn')->unique())->toHaveCount(50)
        ->and($books->pluck('slug')->unique())->toHaveCount(50);

    foreach ($books as $book) {
        expect($book->isbn)->toMatch('/^\d{13}$/')
            ->and($book->price)->toBeInt()
            ->and($book->meta)->toBeArray();

        $sum = 0;
        foreach (str_split($book->isbn) as $i => $digit) {
            $sum += (int) $digit * ($i % 2 === 0 ? 1 : 3);
        }
        expect($sum % 10)->toBe(0); // EAN-13 checksum
    }
});

test('review approved state', function () {
    expect(Review::factory()->create()->is_approved)->toBeFalse()
        ->and(Review::factory()->approved()->create()->is_approved)->toBeTrue();
});

test('genre child state links to a parent', function () {
    $child = Genre::factory()->child()->create();
    $parent = Genre::factory()->create();
    $explicit = Genre::factory()->child($parent)->create();

    expect(Genre::find($child->parent_id))->not->toBeNull()
        ->and($explicit->parent_id)->toBe($parent->id);
});

test('enum casts are applied', function () {
    expect(Image::factory()->create()->type)->toBe(ImageType::Cover)
        ->and(StockMovement::factory()->create()->reason)->toBe(StockMovementReason::Receipt);
});
