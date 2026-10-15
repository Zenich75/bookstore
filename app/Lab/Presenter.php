<?php

namespace App\Lab;

use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

/**
 * Turns lab results (models, collections, scalars) into plain arrays for display.
 * Uses only loaded data (toArray), never triggers queries.
 */
final class Presenter
{
    public static function present(mixed $value, int $items = 5): mixed
    {
        return match (true) {
            $value instanceof Model => $value->toArray(),
            $value instanceof Collection, $value instanceof LazyCollection => [
                'count' => $value->count(),
                'items' => $value->take($items)->map(fn ($item) => self::present($item, $items))->values()->all(),
            ],
            is_array($value) => array_map(fn ($item) => self::present($item, $items), $value),
            $value instanceof BackedEnum => $value->value,
            $value instanceof DateTimeInterface => $value->format('Y-m-d H:i:s'),
            default => $value,
        };
    }
}
