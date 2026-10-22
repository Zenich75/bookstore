<?php

namespace App\Http\Requests\Admin;

use App\Enums\AuthorRole;
use App\Enums\BookFormat;
use App\Models\Book;
use App\Rules\ValidIsbn13;
use App\ValueObjects\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Lifecycle (FormRequest::validateResolved()): prepareForValidation() -> authorize()
 * -> rules() + after() -> passedValidation(); a failure redirects back with errors
 * and old input before the controller action runs.
 */
class StoreBookRequest extends FormRequest
{
    /**
     * Through the policy (BookPolicy::manage). The #[Authorize] attribute on the
     * controller checks the same ability; the request keeps its own check so it
     * stays safe wherever it is reused.
     */
    public function authorize(): bool
    {
        return $this->user()->can('manage', Book::class);
    }

    /**
     * The book being edited; null on create. UpdateBookRequest overrides it.
     */
    public function book(): ?Book
    {
        return null;
    }

    /**
     * Normalize input before the rules see it. Only this request's copy of the input
     * changes: old() still shows what the user typed ("978-617-…", "1 299,90").
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            // "978-617-0000-01-9" / "978 617 0000019" => "9786170000019"
            'isbn' => preg_replace('/\D+/', '', (string) $this->input('isbn')),
            // "1 299,90" => "1299.90" — still a string: no float ever touches money.
            'price' => str_replace([' ', "\u{A0}", ','], ['', '', '.'], trim((string) $this->input('price'))),
            'slug' => $this->filled('slug') ? Str::slug((string) $this->input('slug'), '-', 'uk') : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            // Optional on create (HasSlug generates it from the title).
            // Rule::unique() has no soft-delete filter by default, so trashed books count
            // too — as they must: the DB unique index covers them. Adding ->withoutTrashed()
            // would let a duplicate pass validation and fail on INSERT with a 500.
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('books', 'slug')->ignore($this->book())],
            // bail: no uniqueness query for a value that is not even an ISBN.
            'isbn' => ['bail', 'required', 'string', new ValidIsbn13, Rule::unique('books', 'isbn')->ignore($this->book())],
            'price' => ['required', 'string', 'regex:/^\d{1,7}(\.\d{1,2})?$/'],
            'format' => ['required', Rule::enum(BookFormat::class)],
            'genre_id' => ['required', 'integer', Rule::exists('genres', 'id')],
            // books.published_at is a TIMESTAMP column: 1970-01-01 .. 2038-01-19 only.
            'published_at' => ['nullable', 'date', 'after:1970-01-01', 'before:2038-01-19'],
            // Array rules: "authors" itself, then every row via the * wildcard.
            'authors' => ['array', 'max:10'],
            // nullable: an empty row is skipped (null => no other rule runs for it);
            // distinct: the same author twice in the list is an error on both rows.
            'authors.*.id' => ['nullable', 'integer', 'distinct', Rule::exists('authors', 'id')],
            'authors.*.role' => ['required_with:authors.*.id', Rule::enum(AuthorRole::class)],
            'authors.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:99'],
            'genres' => ['array'],
            'genres.*' => ['integer', Rule::exists('genres', 'id')],
            'tags' => ['array'],
            'tags.*' => ['integer', Rule::exists('tags', 'id')],
        ];
    }

    /**
     * Columns for Book::create()/update(), built from validated data only.
     *
     * @return array<string, mixed>
     */
    public function bookAttributes(): array
    {
        $publishedAt = $this->safe()->published_at ? Carbon::parse($this->safe()->published_at) : null;

        return [
            // safe()->only(): an explicit whitelist on top of validation.
            ...$this->safe()->only(['title', 'isbn', 'format', 'genre_id']),
            // A blank slug: generated on create (HasSlug), kept as is on update.
            ...array_filter(['slug' => $this->safe()->slug]),
            'price' => Money::fromUah($this->safe()->price),
            'published_at' => $publishedAt,
            'published_year' => $publishedAt?->year,
        ];
    }

    /**
     * sync() payload: [author_id => pivot columns]. Rows are ordered by sort_order
     * (row position when blank) and renumbered 0, 1, 2, ...
     *
     * @return array<int, array{role: string, sort_order: int}>
     */
    public function authorCredits(): array
    {
        return collect($this->validated('authors', []))
            ->filter(fn (array $row) => filled($row['id'] ?? null))
            ->values()
            ->sortBy(fn (array $row, int $position) => [(int) ($row['sort_order'] ?? $position), $position])
            ->values()
            ->mapWithKeys(fn (array $row, int $i) => [(int) $row['id'] => ['role' => $row['role'], 'sort_order' => $i]])
            ->all();
    }

    /**
     * The main genre is books.genre_id; it is not repeated as a secondary one.
     *
     * @return list<int>
     */
    public function secondaryGenreIds(): array
    {
        return array_values(array_diff(array_map('intval', $this->validated('genres', [])), [(int) $this->validated('genre_id')]));
    }

    /**
     * @return list<int>
     */
    public function tagIds(): array
    {
        return array_map('intval', $this->validated('tags', []));
    }
}
