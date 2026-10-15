<?php

namespace Database\Seeders;

use App\Enums\StockMovementReason;
use App\Enums\UserRole;
use Database\Seeders\Support\BulkInserts;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StockSeeder extends Seeder
{
    use BulkInserts;

    public function run(): void
    {
        $now = now();
        $warehouseIds = DB::table('warehouses')->pluck('id')->all();
        // warehouse_id => manager user_id (who "received" the initial stock).
        $managers = DB::table('users')->where('role', UserRole::Manager->value)
            ->pluck('id', 'warehouse_id')->all();

        DB::table('books')->select('id')->chunkById(1000, function ($books) use ($now, $warehouseIds, $managers) {
            $stocks = [];
            $movements = [];

            foreach ($books as $book) {
                // Each book is stocked in 1-4 warehouses.
                foreach ($this->pickRandom($warehouseIds, mt_rand(1, 4)) as $warehouseId) {
                    $quantity = $this->randomQuantity();

                    $stocks[] = [
                        'warehouse_id' => $warehouseId,
                        'book_id' => $book->id,
                        'quantity' => $quantity,
                        'reserved' => $quantity > 0 && mt_rand(1, 100) <= 20 ? mt_rand(0, min(3, $quantity)) : 0,
                        'shelf_code' => sprintf('%s-%02d-%d', chr(mt_rand(65, 72)), mt_rand(1, 40), mt_rand(1, 6)),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    // Initial receipt that explains the current quantity.
                    $movements[] = [
                        'warehouse_id' => $warehouseId,
                        'book_id' => $book->id,
                        'user_id' => $managers[$warehouseId] ?? null,
                        'delta' => $quantity,
                        'reason' => StockMovementReason::Receipt->value,
                        'created_at' => $now,
                    ];
                }
            }

            DB::transaction(function () use ($stocks, $movements) {
                $this->insertChunked('stocks', $stocks);
                $this->insertChunked('stock_movements', $movements);
            });
        });
    }

    /**
     * ~10% out of stock, ~15% low stock (1-4), the rest 5-250.
     */
    private function randomQuantity(): int
    {
        $roll = mt_rand(1, 100);

        return match (true) {
            $roll <= 10 => 0,
            $roll <= 25 => mt_rand(1, 4),
            default => mt_rand(5, 250),
        };
    }
}
