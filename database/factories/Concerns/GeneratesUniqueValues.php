<?php

namespace Database\Factories\Concerns;

use Illuminate\Support\Str;

/**
 * Collision-free unique values for large seeds.
 *
 * faker->unique() keeps every generated value in memory and retries on
 * collision, which gets slow (and eventually throws) at 50k+ rows. A
 * per-process counter started at a random offset is unique by construction
 * and still differs between seed runs.
 */
trait GeneratesUniqueValues
{
    protected static int $uniqueSequence = 0;

    protected static ?int $uniqueOffset = null;

    protected static function nextUniqueNumber(): int
    {
        static::$uniqueOffset ??= random_int(0, 100_000_000);

        return static::$uniqueOffset + ++static::$uniqueSequence;
    }

    /**
     * "Тіні забутих предків" => "tini-zabutykh-predkiv-2bx9k" (Ukrainian transliteration,
     * ASCII only, base36 counter suffix).
     */
    protected static function uniqueSlug(string $value): string
    {
        return Str::slug($value, '-', 'uk').'-'.base_convert((string) static::nextUniqueNumber(), 10, 36);
    }

    /**
     * Valid ISBN-13 (978 prefix + 9-digit sequence + EAN-13 check digit).
     */
    protected static function uniqueIsbn(): string
    {
        $body = '978'.str_pad((string) (static::nextUniqueNumber() % 1_000_000_000), 9, '0', STR_PAD_LEFT);

        $sum = 0;
        foreach (str_split($body) as $i => $digit) {
            $sum += (int) $digit * ($i % 2 === 0 ? 1 : 3);
        }

        return $body.((10 - $sum % 10) % 10);
    }
}
