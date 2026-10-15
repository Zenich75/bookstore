<?php

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

test('a user can review a book only once', function () {
    $user = User::factory()->create();
    $book = Book::factory()->create();

    Review::factory()->create(['user_id' => $user->id, 'book_id' => $book->id]);

    expect(fn () => Review::factory()->create(['user_id' => $user->id, 'book_id' => $book->id]))
        ->toThrow(UniqueConstraintViolationException::class);
});

test('a book has one stock row per warehouse', function () {
    $warehouse = Warehouse::factory()->create();
    $book = Book::factory()->create();
    $row = ['warehouse_id' => $warehouse->id, 'book_id' => $book->id, 'quantity' => 1];

    DB::table('stocks')->insert($row);

    expect(fn () => DB::table('stocks')->insert($row))
        ->toThrow(UniqueConstraintViolationException::class);
});
