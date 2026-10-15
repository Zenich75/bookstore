<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Custom pivot for warehouses <-> books (the "stocks" table).
 *
 * Unlike a plain pivot, stocks has its own auto-increment id and data columns,
 * so it is a model you can also query directly (Book::stocks(), Warehouse::stocks()).
 */
#[Table(name: 'stocks', incrementing: true)]
#[Fillable(['warehouse_id', 'book_id', 'quantity', 'reserved', 'shelf_code'])]
class Stock extends Pivot
{
    // Legacy syntax (Laravel <= 12), equivalent to the #[Table] attribute above.
    // Pivot sets $incrementing = false by default; without true, the id generated
    // on insert is not set on the model.
    // protected $table = 'stocks';
    // public $incrementing = true;

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * @return BelongsTo<Book, $this>
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    /**
     * Units that can still be sold (preview of accessors, stage 6).
     *
     * @return Attribute<int, never>
     */
    protected function available(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes) => max(0, $attributes['quantity'] - $attributes['reserved']),
        );
    }
}
