<?php

use App\ValueObjects\Money;

test('fromUah parses strings without float math', function (string|int $input, int $cents) {
    expect(Money::fromUah($input)->cents)->toBe($cents);
})->with([
    ['199.99', 19999], ['199,99', 19999], ['0.1', 10], ['0.01', 1], ['12', 1200],
    ['-5.50', -550], [' 7.05 ', 705], [250, 25000],
]);

test('fromUah rejects malformed amounts', function (string $input) {
    expect(fn () => Money::fromUah($input))->toThrow(InvalidArgumentException::class);
})->with(['', '1.234', 'abc', '1e3', '12.']);

test('arithmetic is exact and immutable', function () {
    $price = Money::fromUah('0.10');
    $sum = $price->add(Money::fromUah('0.20'));

    expect($sum->cents)->toBe(30)
        ->and($price->cents)->toBe(10) // unchanged
        ->and(Money::fromUah('19.99')->multiply(3)->cents)->toBe(5997)
        ->and(Money::fromCents(5)->multiply(0)->cents)->toBe(0);
});

test('toUah and format', function () {
    expect(Money::fromCents(123450)->toUah())->toBe('1234.50')
        ->and(Money::fromCents(5)->toUah())->toBe('0.05')
        ->and(Money::fromCents(-550)->toUah())->toBe('-5.50')
        // intl: no-break space (U+00A0) as the thousands separator.
        ->and(Money::fromCents(123450)->format())->toBe("1\u{A0}234,50\u{A0}₴")
        ->and((string) Money::fromCents(100))->toBe(Money::fromCents(100)->format());
});

test('serializes as cents + formatted', function () {
    expect(json_decode(json_encode(Money::fromCents(9900)), true))
        ->toBe(['cents' => 9900, 'formatted' => Money::fromCents(9900)->format()]);
});
