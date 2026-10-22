<?php

namespace App\Http\Requests;

use App\Models\Book;
use App\Models\Review;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class StoreReviewRequest extends FormRequest
{
    public const BODY_MIN = 20;

    public const BODY_MAX = 2000;

    /**
     * ReviewPolicy::create: a verified user without a review of this book (pending
     * ones included). false => 403 before any rule runs.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', [Review::class, $this->book()]);
    }

    public function book(): Book
    {
        return $this->route('book');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'between:1,5'],
            'body' => [
                'required', 'string', 'min:'.self::BODY_MIN, 'max:'.self::BODY_MAX,
                // Closure rule: a one-off check that does not deserve a class. Links in
                // reviews are mostly spam; reviews are about the book, not other sites.
                function (string $attribute, mixed $value, Closure $fail) {
                    if (preg_match('~(https?://|www\.)~i', (string) $value)) {
                        $fail('validation.no_links')->translate();
                    }
                },
            ],
        ];
    }

    /**
     * Back to the form, not to the top of a long book page.
     */
    protected function getRedirectUrl(): string
    {
        return parent::getRedirectUrl().'#review-form';
    }
}
