<?php

use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\LogSlowRequests;
use App\Models\Book;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Status codes for one URL per area, by role.
 */
test('role middleware per area', function (string $role, array $expected) {
    $user = match ($role) {
        'reader' => asReader(),
        'manager' => asManager(),
        'admin' => asAdmin(),
    };

    $statuses = [
        'manage' => $this->get(route('manage.warehouses.index'))->status(),
        'reports' => $this->get(route('reports.top-authors'))->status(),
        'admin' => $this->get(route('admin.dashboard'))->status(),
    ];

    expect($statuses)->toBe($expected);
})->with([
    'reader' => ['reader', ['manage' => 403, 'reports' => 403, 'admin' => 403]],
    'manager' => ['manager', ['manage' => 200, 'reports' => 200, 'admin' => 403]],
    'admin' => ['admin', ['manage' => 200, 'reports' => 200, 'admin' => 200]],
]);

test('a typo in role:... parameters fails loudly', function () {
    $request = Request::create('/');
    $request->setUserResolver(fn () => User::factory()->admin()->make());

    expect(fn () => (new EnsureUserHasRole)->handle($request, fn () => response('ok'), 'admn'))
        ->toThrow(ValueError::class);
});

test('warehouse.access: a manager opens only their own warehouse; the admin any', function () {
    [$own, $other] = Warehouse::factory()->count(2)->create();

    asManager($own);
    $this->get(route('manage.warehouses.stocks.index', $own))->assertOk();
    $this->get(route('manage.warehouses.movements.index', $own))->assertOk();
    $this->get(route('manage.warehouses.stocks.index', $other))->assertForbidden();
    $this->get(route('manage.warehouses.movements.index', $other))->assertForbidden();
    $this->get(route('manage.warehouses.index'))->assertSee($own->name)->assertDontSee($other->name);

    asAdmin();
    $this->get(route('manage.warehouses.stocks.index', $other))->assertOk();
    $this->get(route('manage.warehouses.index'))->assertSee($own->name)->assertSee($other->name);
});

test('crafted URLs cannot reach another warehouse\'s stock (IDOR)', function () {
    [$own, $other] = Warehouse::factory()->count(2)->create();
    $book = Book::factory()->create();
    $own->books()->attach($book, ['quantity' => 5]);
    $other->books()->attach($book, ['quantity' => 7]);
    $ownStock = $own->stocks()->first();
    $foreignStock = $other->stocks()->first();

    asManager($own);

    $this->get(route('manage.warehouses.stocks.edit', [$own, $ownStock]))->assertOk();
    // Own warehouse in the URL, foreign stock id: scopeBindings() => 404.
    $this->get("/manage/warehouses/{$own->id}/stocks/{$foreignStock->id}/edit")->assertNotFound();
    $this->put("/manage/warehouses/{$own->id}/stocks/{$foreignStock->id}", ['quantity' => 0, 'reserved' => 0, 'reason' => 'sale'])->assertNotFound();
    // Foreign warehouse in the URL: warehouse.access => 403.
    $this->put(route('manage.warehouses.stocks.update', [$other, $foreignStock]), ['quantity' => 0, 'reserved' => 0, 'reason' => 'sale'])->assertForbidden();

    expect($foreignStock->fresh()->quantity)->toBe(7);
});

test('LogSlowRequests logs above the threshold in terminate(), thanks to the singleton', function () {
    expect(app(LogSlowRequests::class))->toBe(app(LogSlowRequests::class));

    Log::spy();
    config(['app.slow_request_ms' => -1]); // every request is "slow"

    $this->get(route('about'))->assertOk();

    Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message, array $context) => $message === 'Slow request'
        && $context['url'] === route('about') && $context['status'] === 200 && $context['ms'] >= 0);
});

test('LogSlowRequests: below the threshold nothing is logged', function () {
    Log::spy();
    config(['app.slow_request_ms' => 60_000]);

    $this->get(route('about'))->assertOk();

    Log::shouldNotHaveReceived('warning');
});

test('without the singleton binding terminate() runs on a fresh instance and logs nothing', function () {
    app()->bind(LogSlowRequests::class); // new instance per resolve
    Log::spy();
    config(['app.slow_request_ms' => -1]);

    $this->get(route('about'))->assertOk();

    Log::shouldNotHaveReceived('warning');
});

test('X-Query-Count header only in the local environment', function () {
    $this->get(route('about'))->assertHeaderMissing('X-Query-Count');

    app()['env'] = 'local';

    $this->get(route('books.index'))->assertHeader('X-Query-Count');
    expect((int) $this->get(route('books.index'))->headers->get('X-Query-Count'))->toBeGreaterThan(0);
});

test('lab pages: open in local/testing, admin-only elsewhere', function () {
    $this->get(route('lab.models'))->assertOk(); // testing, guest

    app()['env'] = 'production';

    $this->get(route('lab.relations'))->assertForbidden();
    asReader();
    $this->get(route('lab.relations'))->assertForbidden();
    asAdmin();
    $this->get(route('lab.relations'))->assertOk();
});
