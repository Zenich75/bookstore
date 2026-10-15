<?php

namespace App\Reports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Best rated authors by the average of approved reviews of their books.
 *
 * Only author/co-author credits count (not translations). Credits are made
 * distinct first, so a review is never counted twice for the same author.
 * books_count = the author's books that have approved reviews.
 */
final class TopAuthorsReport
{
    public function __construct(
        private readonly int $minReviews = 10,
        private readonly int $limit = 20,
    ) {}

    /**
     * @return Collection<int, object{id: int, first_name: string, last_name: string, slug: string, avg_rating: float, reviews_count: int, books_count: int}>
     */
    public function rows(): Collection
    {
        $credits = DB::table('author_book')
            ->select(['author_id', 'book_id'])
            ->whereIn('role', ['author', 'co_author'])
            ->distinct();

        // Approved review stats per (non-deleted) book, computed once — joining raw reviews
        // to every credit first was ~9x slower on 200k reviews.
        $bookReviews = DB::table('reviews')
            ->join('books', 'books.id', '=', 'reviews.book_id')
            ->where('reviews.is_approved', true)
            ->whereNull('books.deleted_at')
            ->groupBy('reviews.book_id')
            ->select('reviews.book_id')
            ->selectRaw('sum(reviews.rating) as rating_sum')
            ->selectRaw('count(*) as reviews_count');

        return DB::table('authors')
            ->joinSub($credits, 'credits', 'credits.author_id', '=', 'authors.id')
            ->joinSub($bookReviews, 'book_reviews', 'book_reviews.book_id', '=', 'credits.book_id')
            ->groupBy('authors.id', 'authors.first_name', 'authors.last_name', 'authors.slug')
            ->select(['authors.id', 'authors.first_name', 'authors.last_name', 'authors.slug'])
            // Weighted average over all reviews; "* 1.0" avoids integer division on SQLite.
            ->selectRaw('sum(book_reviews.rating_sum) * 1.0 / sum(book_reviews.reviews_count) as avg_rating')
            ->selectRaw('sum(book_reviews.reviews_count) as reviews_count')
            ->selectRaw('count(*) as books_count')
            // Raw expression with a bound parameter: the value travels separately from the SQL.
            ->havingRaw('sum(book_reviews.reviews_count) >= ?', [$this->minReviews])
            ->orderByDesc('avg_rating')
            ->orderByDesc('reviews_count')
            ->orderBy('authors.id')
            ->limit($this->limit)
            ->get()
            ->map(function (object $row) {
                $row->avg_rating = round((float) $row->avg_rating, 2);
                $row->reviews_count = (int) $row->reviews_count;
                $row->books_count = (int) $row->books_count;

                return $row;
            });
    }

    public function minReviews(): int
    {
        return $this->minReviews;
    }
}
