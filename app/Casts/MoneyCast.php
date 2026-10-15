<?php

namespace App\Casts;

use App\ValueObjects\Money;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\SerializesCastableAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Integer kopecks in the database <=> Money in the model.
 *
 * Used via Money::castUsing(), so a model writes 'price' => Money::class.
 *
 * @implements CastsAttributes<Money, Money|int>
 */
class MoneyCast implements CastsAttributes, SerializesCastableAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        return $value === null ? null : Money::fromCents((int) $value);
    }

    /**
     * Accepts Money or integer kopecks. A float is rejected: 12.5 could mean
     * 12.50 ₴ or 12.5 kopecks, and floats round (19.99 * 100 = 1998.9999...).
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?int
    {
        return match (true) {
            $value === null => null,
            $value instanceof Money => $value->cents,
            is_int($value) => $value,
            is_float($value) => throw new InvalidArgumentException(sprintf(
                '%s::$%s does not accept floats (%s given). Pass integer kopecks or Money::fromUah(\'%s\').',
                $model::class, $key, var_export($value, true), number_format($value, 2, '.', ''),
            )),
            default => throw new InvalidArgumentException(sprintf(
                '%s::$%s expects Money or integer kopecks, %s given.', $model::class, $key, get_debug_type($value),
            )),
        };
    }

    /**
     * toArray()/toJson(): {"cents": 12345, "formatted": "123,45 ₴"}.
     *
     * @return array{cents: int, formatted: string}|null
     */
    public function serialize(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        return $value?->toArray();
    }
}
