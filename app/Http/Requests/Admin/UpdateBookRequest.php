<?php

namespace App\Http\Requests\Admin;

use App\Models\Book;

/**
 * Same rules as on create; only the book differs: the unique rules in
 * StoreBookRequest::rules() ignore it via Rule::unique(...)->ignore($this->book()),
 * so saving a book with its own ISBN/slug is not a "duplicate".
 */
class UpdateBookRequest extends StoreBookRequest
{
    public function book(): Book
    {
        // The {book} route parameter, already resolved by route model binding.
        return $this->route('book');
    }
}
