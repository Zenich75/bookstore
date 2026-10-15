<?php

namespace Database\Seeders;

use App\Models\Review;
use Database\Seeders\Support\BulkInserts;
use Database\Seeders\Support\SeedScale;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ReviewSeeder extends Seeder
{
    use BulkInserts;

    public function run(): void
    {
        $scale = SeedScale::current();
        $userIds = DB::table('users')->pluck('id')->all();
        $maxPerBook = $scale->avgReviewsPerBook * 2;

        DB::table('books')->select('id')->chunkById(1000, function ($books) use ($userIds, $maxPerBook) {
            $rows = [];

            foreach ($books as $book) {
                // Distinct users per book => unique(user_id, book_id) always holds.
                foreach ($this->pickRandom($userIds, mt_rand(0, $maxPerBook)) as $userId) {
                    $createdAt = now()->subMinutes(mt_rand(0, 60 * 24 * 365 * 3));

                    // Explicit user_id/book_id keep raw() from creating related models.
                    $rows[] = Review::factory()->raw([
                        'user_id' => $userId,
                        'book_id' => $book->id,
                        'is_approved' => mt_rand(1, 100) <= 80,
                    ]) + ['created_at' => $createdAt, 'updated_at' => $createdAt];
                }
            }

            DB::transaction(fn () => $this->insertChunked('reviews', $rows));
        });
    }
}
