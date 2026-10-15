<?php

namespace Database\Seeders\Support;

/**
 * Data volume for DatabaseSeeder, selected by SEED_SCALE (config('database.seed_scale')).
 */
final readonly class SeedScale
{
    public function __construct(
        public string $name,
        public int $books,
        public int $authors,
        public int $users,
        public int $avgReviewsPerBook,
    ) {}

    public static function current(): self
    {
        return match (config('database.seed_scale')) {
            'full' => new self('full', books: 50_000, authors: 5_000, users: 2_000, avgReviewsPerBook: 4),
            default => new self('small', books: 500, authors: 150, users: 100, avgReviewsPerBook: 4),
        };
    }
}
