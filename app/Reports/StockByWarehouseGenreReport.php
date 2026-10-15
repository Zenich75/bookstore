<?php

namespace App\Reports;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

/**
 * Stock per warehouse and root genre, aggregated in SQL.
 *
 * Child genres are rolled up into their root: COALESCE(genres.parent_id, genres.id)
 * is the root id for a two-level tree. Soft-deleted books are excluded.
 * Aggregating on ids first and joining names afterwards keeps the grouped rows narrow
 * (~0.3 s instead of ~1 s on 125k stock rows).
 * Money is in cents (quantity * books.price).
 */
final class StockByWarehouseGenreReport
{
    public function __construct(
        private readonly ?int $warehouseId = null,
    ) {}

    /**
     * @return Collection<int, object{warehouse_id: int, warehouse: string, genre_id: int, genre: string, quantity: int, reserved: int, books: int, value_cents: int}>
     */
    public function rows(): Collection
    {
        // Step 1: aggregate on ids only (narrow rows to group and sort).
        $totals = $this->baseQuery()
            ->join('genres', 'genres.id', '=', 'books.genre_id')
            ->groupBy('stocks.warehouse_id')
            // Root genre of a two-level tree. A fixed expression: no user input involved.
            ->groupByRaw('coalesce(genres.parent_id, genres.id)')
            ->select('stocks.warehouse_id')
            ->selectRaw('coalesce(genres.parent_id, genres.id) as genre_id')
            ->selectRaw('sum(stocks.quantity) as quantity')
            ->selectRaw('sum(stocks.reserved) as reserved')
            ->selectRaw('count(distinct stocks.book_id) as books')
            ->selectRaw('sum(stocks.quantity * books.price) as value_cents');

        // Step 2: attach names to the few aggregated rows.
        return DB::query()
            ->fromSub($totals, 'totals')
            ->join('warehouses', 'warehouses.id', '=', 'totals.warehouse_id')
            ->join('genres', 'genres.id', '=', 'totals.genre_id')
            ->select([
                'totals.warehouse_id',
                'warehouses.name as warehouse',
                'totals.genre_id',
                'genres.name as genre',
                'totals.quantity',
                'totals.reserved',
                'totals.books',
                'totals.value_cents',
            ])
            ->orderBy('warehouses.name')
            ->orderBy('genres.name')
            ->get()
            // Aggregates come back as strings/decimals on some drivers: normalize to int.
            ->map(function (object $row) {
                foreach (['warehouse_id', 'genre_id', 'quantity', 'reserved', 'books', 'value_cents'] as $column) {
                    $row->{$column} = (int) $row->{$column};
                }

                return $row;
            });
    }

    /**
     * Grand totals of the given rows (a handful of rows: summing in PHP is fine).
     *
     * @param  Collection<int, object>  $rows
     * @return array{quantity: int, reserved: int, value_cents: int}
     */
    public function totals(Collection $rows): array
    {
        return [
            'quantity' => (int) $rows->sum('quantity'),
            'reserved' => (int) $rows->sum('reserved'),
            'value_cents' => (int) $rows->sum('value_cents'),
        ];
    }

    /**
     * One line per stock row for the CSV export, streamed in chunks of 1000.
     *
     * @return LazyCollection<int, object>
     */
    public function lines(): LazyCollection
    {
        return $this->baseQuery()
            // MySQL misjudges "books.deleted_at is null", drives the join from books and
            // filesorts every chunk (~190 ms each at 125k rows). Restricting stocks to its
            // primary key makes it walk stocks by id: ~2 ms per chunk. (No-op on SQLite.)
            ->useIndex('primary')
            ->select([
                'stocks.id',
                'warehouses.name as warehouse',
                'stocks.shelf_code',
                'books.isbn',
                'books.title',
                'stocks.quantity',
                'stocks.reserved',
                'books.price',
            ])
            ->lazyById(1000, 'stocks.id', 'id');
    }

    private function baseQuery(): Builder
    {
        return DB::table('stocks')
            ->join('books', 'books.id', '=', 'stocks.book_id')
            ->join('warehouses', 'warehouses.id', '=', 'stocks.warehouse_id')
            ->whereNull('books.deleted_at')
            // A bound parameter (where(column, value)), never string interpolation.
            ->when($this->warehouseId, fn (Builder $query, int $id) => $query->where('stocks.warehouse_id', $id));
    }
}
