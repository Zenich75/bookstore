<?php

use App\Models\Book;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    app()->setLocale('uk');
    $this->warehouse = Warehouse::factory()->create();
    $this->warehouse->books()->attach(Book::factory()->create(), ['quantity' => 10, 'reserved' => 0, 'shelf_code' => 'A-01-1']);
    $this->stock = $this->warehouse->stocks()->first();
    asManager($this->warehouse);
});

function putStock(array $data): TestResponse
{
    return test()->put(route('manage.warehouses.stocks.update', [test()->warehouse, test()->stock]), $data);
}

test('UpdateStockRequest rules', function (array $data, array $errors) {
    $response = putStock($data);

    $errors ? $response->assertSessionHasErrors($errors) : $response->assertSessionHasNoErrors();
})->with([
    'valid sale' => [['quantity' => 8, 'reserved' => 2, 'shelf_code' => 'A-01-1', 'reason' => 'sale'], []],
    'reserved equals quantity' => [['quantity' => 3, 'reserved' => 3, 'shelf_code' => 'A-01-1', 'reason' => 'sale'], []],
    'after(): reserved above quantity' => [['quantity' => 1, 'reserved' => 2, 'shelf_code' => 'A-01-1', 'reason' => 'sale'], ['reserved']],
    'negative quantity' => [['quantity' => -1, 'reserved' => 0, 'shelf_code' => 'A-01-1', 'reason' => 'sale'], ['quantity']],
    'not a number' => [['quantity' => 'ten', 'reserved' => 0, 'shelf_code' => 'A-01-1', 'reason' => 'sale'], ['quantity']],
    'requiredIf: shelf needed while copies remain' => [['quantity' => 5, 'reserved' => 0, 'shelf_code' => '', 'reason' => 'sale'], ['shelf_code']],
    'requiredIf: no shelf for an empty stock' => [['quantity' => 0, 'reserved' => 0, 'shelf_code' => '', 'reason' => 'sale'], []],
    'Rule::when: reason required when quantity changes' => [['quantity' => 9, 'reserved' => 0, 'shelf_code' => 'A-01-1'], ['reason']],
    'Rule::when: no reason needed for a shelf move' => [['quantity' => 10, 'reserved' => 0, 'shelf_code' => 'B-02-2'], []],
    'import is not a manual reason' => [['quantity' => 9, 'reserved' => 0, 'shelf_code' => 'A-01-1', 'reason' => 'import'], ['reason']],
]);

test('after() adds its message only when both numbers are valid', function () {
    putStock(['quantity' => 1, 'reserved' => 2, 'shelf_code' => 'A-01-1', 'reason' => 'sale'])
        ->assertSessionHasErrors(['reserved' => __('validation.reserved_exceeds_quantity')]);

    // quantity itself is invalid: only its own error, no cross-field noise.
    putStock(['quantity' => 'x', 'reserved' => 2, 'shelf_code' => 'A-01-1', 'reason' => 'sale'])
        ->assertSessionHasErrors('quantity')
        ->assertSessionDoesntHaveErrors('reserved');
});

test('custom per-attribute message for shelf_code.required', function () {
    putStock(['quantity' => 5, 'reserved' => 0, 'shelf_code' => '', 'reason' => 'sale'])
        ->assertSessionHasErrors(['shelf_code' => 'Вкажіть код полиці: на складі є примірники.']);
});

test('a shelf move without a reason writes no movement', function () {
    $before = StockMovement::count();

    putStock(['quantity' => 10, 'reserved' => 0, 'shelf_code' => 'B-02-2'])->assertRedirect();

    expect($this->stock->fresh()->shelf_code)->toBe('B-02-2')
        ->and(StockMovement::count())->toBe($before);
});

test('a manager of another warehouse cannot submit the form', function () {
    asManager();

    putStock(['quantity' => 0, 'reserved' => 0, 'reason' => 'sale'])->assertForbidden();
    expect($this->stock->fresh()->quantity)->toBe(10);
});
