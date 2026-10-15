<?php

namespace App\ValueObjects;

use App\Casts\MoneyCast;
use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Number;
use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * An amount of hryvnias, stored as integer kopecks (cents).
 *
 * Immutable: every operation returns a new instance. No float arithmetic anywhere —
 * floats cannot represent 0.10 exactly, so 0.1 + 0.2 !== 0.3. Division by 100 happens
 * only in format(), at the presentation layer.
 *
 * Castable: a model can declare 'price' => Money::class and Eloquent uses MoneyCast.
 *
 * @implements Arrayable<string, int|string>
 */
final readonly class Money implements Arrayable, Castable, JsonSerializable, Stringable
{
    public const CURRENCY = 'UAH';

    private function __construct(public int $cents) {}

    public static function fromCents(int $cents): self
    {
        return new self($cents);
    }

    /**
     * "123", "123.4", "123,45", "-5.00" or 123 => kopecks, parsed as strings (no float math).
     */
    public static function fromUah(string|int $amount): self
    {
        if (is_int($amount)) {
            return new self($amount * 100);
        }

        if (! preg_match('/^(-?)(\d+)(?:[.,](\d{1,2}))?$/', trim($amount), $m)) {
            throw new InvalidArgumentException("Invalid UAH amount [{$amount}]: expected digits with up to 2 decimals, e.g. \"123.45\".");
        }

        $cents = (int) $m[2] * 100 + (int) str_pad($m[3] ?? '0', 2, '0');

        return new self($m[1] === '-' ? -$cents : $cents);
    }

    public function add(Money $other): self
    {
        return new self($this->cents + $other->cents);
    }

    public function multiply(int $times): self
    {
        return new self($this->cents * $times);
    }

    /**
     * "1 234,50 ₴" (Ukrainian locale; intl uses a no-break space as the thousands separator).
     */
    public function format(): string
    {
        return Number::currency($this->cents / 100, in: self::CURRENCY, locale: 'uk');
    }

    /**
     * "1234.50" — exact decimal string, e.g. for forms or CSV.
     */
    public function toUah(): string
    {
        $sign = $this->cents < 0 ? '-' : '';
        $abs = abs($this->cents);

        return $sign.intdiv($abs, 100).'.'.str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function castUsing(array $arguments): string
    {
        return MoneyCast::class;
    }

    /**
     * @return array{cents: int, formatted: string}
     */
    public function toArray(): array
    {
        return ['cents' => $this->cents, 'formatted' => $this->format()];
    }

    /**
     * @return array{cents: int, formatted: string}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function __toString(): string
    {
        return $this->format();
    }
}
