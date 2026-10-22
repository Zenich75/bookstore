<?php

namespace App\Http\Requests;

use App\Models\Review;

class UpdateReviewRequest extends StoreReviewRequest
{
    /**
     * Named error bag: errors go to $errors->reviewEdit (@error('body', 'reviewEdit')),
     * so they cannot be confused with errors of another form on the same page.
     *
     * @var string
     */
    protected $errorBag = 'reviewEdit';

    /**
     * ReviewPolicy::update: own review, and only while it awaits moderation.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->review());
    }

    public function review(): Review
    {
        return $this->route('review');
    }

    /**
     * "sometimes": a field is validated only when present, so a client may send just
     * the rating or just the text. Present fields get the full create rules.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_map(fn (array $rules) => ['sometimes', ...$rules], parent::rules());
    }
}
