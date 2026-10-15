<?php

use App\Models\Book;
use App\Models\StockMovement;
use App\Models\Warehouse;

beforeEach(function () {
    $this->warehouse = Warehouse::factory()->create();
    $this->book = Book::factory()->create(['title' => 'Кобзар']);
    $this->warehouse->books()->attach($this->book, ['quantity' => 10, 'reserved' => 0]);
    $this->stock = $this->warehouse->stocks()->first();
});

test('a manager updates stock: the movement records the reason and the user', function () {
    $manager = asManager($this->warehouse);

    $this->get(route('manage.warehouses.stocks.edit', [$this->warehouse, $this->stock]))->assertOk()->assertSee('Кобзар');

    $this->put(route('manage.warehouses.stocks.update', [$this->warehouse, $this->stock]), [
        'quantity' => 7, 'reserved' => 2, 'shelf_code' => 'B-02-3', 'reason' => 'sale',
    ])->assertRedirect(route('manage.warehouses.stocks.index', $this->warehouse))->assertSessionHas('status');

    $movement = StockMovement::latest('id')->first();

    expect($this->stock->fresh()->only(['quantity', 'reserved', 'shelf_code']))->toBe(['quantity' => 7, 'reserved' => 2, 'shelf_code' => 'B-02-3'])
        ->and($movement->delta)->toBe(-3)
        ->and($movement->reason->value)->toBe('sale')
        ->and($movement->user_id)->toBe($manager->id);

    $this->get(route('manage.warehouses.movements.index', $this->warehouse))->assertOk()->assertSee('-3')->assertSee($manager->name);
});

test('stock validation: reserved cannot exceed quantity; import is not a manual reason', function () {
    asManager($this->warehouse);

    $this->put(route('manage.warehouses.stocks.update', [$this->warehouse, $this->stock]), ['quantity' => 1, 'reserved' => 2, 'reason' => 'import'])
        ->assertSessionHasErrors(['reserved', 'reason']);

    expect($this->stock->fresh()->quantity)->toBe(10);
});

test('the public book page shows status only, no per-warehouse data', function () {
    $this->get(route('books.show', $this->book))
        ->assertOk()
        ->assertSee(__('ui.stock_badge.in'))
        ->assertDontSee($this->warehouse->name)
        ->assertDontSee(route('manage.warehouses.index'));
});
