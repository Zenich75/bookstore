<?php

use App\View\Components\StockBadge;

test('stock badge state', function (int $quantity, int $reserved, string $state) {
    $label = __('ui.stock_badge.'.$state);
    $badge = new StockBadge($quantity, $reserved);

    expect($badge->state())->toBe($state)
        ->and($badge->label())->toBe($label);

    $this->component(StockBadge::class, ['quantity' => $quantity, 'reserved' => $reserved])
        ->assertSee($label)
        ->assertSee('data-state="'.$state.'"', false);
})->with([
    'out of stock' => [0, 0, 'out'],
    'fully reserved' => [3, 3, 'out'],
    'low' => [6, 2, 'low'],
    'in stock' => [10, 2, 'in'],
]);
