<?php

namespace Database\Factories;

use App\Enums\StockMovementReason;
use App\Models\Book;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'warehouse_id' => Warehouse::factory(),
            'book_id' => Book::factory(),
            'user_id' => null,
            'delta' => fake()->numberBetween(1, 100),
            'reason' => StockMovementReason::Receipt->value,
        ];
    }
}
