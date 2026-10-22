<?php

use App\Jobs\CheckLowStock;
use App\Models\Book;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Notifications\LowStockNotification;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    app()->setLocale('uk');
    config(['warehouse.low_stock_threshold' => 5]);
});

/**
 * A warehouse with a manager; $stocks = [[quantity, reserved], ...].
 *
 * @return array{0: Warehouse, 1: User}
 */
function warehouseWithStock(array $stocks): array
{
    $warehouse = Warehouse::factory()->create();
    $manager = User::factory()->manager()->create(['warehouse_id' => $warehouse->id]);

    foreach ($stocks as [$quantity, $reserved]) {
        $warehouse->books()->attach(Book::factory()->create(), ['quantity' => $quantity, 'reserved' => $reserved, 'shelf_code' => 'A-01-1']);
    }

    return [$warehouse, $manager];
}

test('one digest per warehouse, to that warehouse\'s manager, listing only low rows', function () {
    Notification::fake();
    [$low, $lowManager] = warehouseWithStock([[2, 0], [10, 7], [50, 0]]); // available 2 and 3 are low
    [, $okManager] = warehouseWithStock([[20, 0]]);
    $trashed = Book::factory()->create();
    $low->books()->attach($trashed, ['quantity' => 0]);
    $trashed->delete(); // soft-deleted books are not reported

    CheckLowStock::dispatch(); // no id: fans out per warehouse (sync queue in tests)

    Notification::assertSentToTimes($lowManager, LowStockNotification::class, 1);
    Notification::assertSentTo($lowManager, LowStockNotification::class, fn (LowStockNotification $n, array $channels) => $n->total === 2
        && collect($n->items)->pluck('available')->all() === [2, 3]
        && $channels === ['mail', 'database']);
    Notification::assertNotSentTo($okManager, LowStockNotification::class);
});

test('the digest is queued on the notifications queue and stored for the bell', function () {
    [$warehouse, $manager] = warehouseWithStock([[1, 0]]);

    CheckLowStock::dispatchSync($warehouse->id);

    expect($manager->unreadNotifications()->count())->toBe(1)
        ->and($manager->notifications()->first()->data['kind'])->toBe('low_stock');
});

test('the digest mail is a Ukrainian markdown mail with a table', function () {
    $notification = new LowStockNotification(1, 'Київ', [['title' => 'Кобзар', 'isbn' => '9786170000019', 'shelf' => 'A-01-1', 'available' => 2]], 1, 5);

    $html = (string) $notification->toMail(User::factory()->make())->render();

    expect($html)->toContain('Склад «Київ»: закінчуються книги')->toContain('Кобзар')->toContain('<table');
});

test('WithoutOverlapping middleware is keyed by warehouse', function () {
    $middleware = (new CheckLowStock(7))->middleware()[0];

    expect($middleware)->toBeInstanceOf(WithoutOverlapping::class)
        ->and($middleware->key)->toBe('warehouse:7');
});

test('the scheduler lists the low-stock check and the movement pruning', function () {
    $this->artisan('schedule:list')
        ->expectsOutputToContain('CheckLowStock')
        ->expectsOutputToContain('model:prune')
        ->assertSuccessful();
});

test('StockMovement::prunable() selects only movements past the retention period', function () {
    config(['warehouse.movements_retention_days' => 30]);
    $old = StockMovement::factory()->create(['created_at' => now()->subDays(31)]);
    $recent = StockMovement::factory()->create(['created_at' => now()->subDays(29)]);

    expect((new StockMovement)->prunable()->pluck('id')->all())->toBe([$old->id]);

    $this->artisan('model:prune', ['--model' => [StockMovement::class]])->assertSuccessful();
    expect(StockMovement::pluck('id')->all())->toBe([$recent->id]);
});
