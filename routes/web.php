<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth;
use App\Http\Controllers\AuthorController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\GenreController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Lab\LabCollectionsController;
use App\Http\Controllers\Lab\LabModelsController;
use App\Http\Controllers\Lab\LabNPlusOneController;
use App\Http\Controllers\Lab\LabRelationsController;
use App\Http\Controllers\Manage;
use App\Http\Controllers\ReportController;
use App\Models\Review;
use App\Models\Scopes\ApprovedScope;
use Illuminate\Support\Facades\Route;

// Invokable controller: a single __invoke() action, referenced by class name.
Route::get('/', HomeController::class)->name('home');

// Resource controller limited to two actions; scoped() binds {book} by slug
// instead of id => /books/{book:slug}.
Route::resource('books', BookController::class)
    ->only(['index', 'show'])
    ->scoped(['book' => 'slug']);

// Controller group: actions are referenced by method name only.
Route::controller(AuthorController::class)->prefix('authors')->name('authors.')->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/{author:slug}', 'show')->name('show');

    // Scoped binding: {book:slug} is looked up via $author->books(),
    // so /authors/a/books/b is a 404 when author "a" didn't write book "b".
    Route::get('/{author:slug}/books/{book:slug}', 'book')->name('books.show')->scopeBindings();
});

// Plain [Controller::class, 'method'] routes; genres.show only redirects.
Route::get('/genres', [GenreController::class, 'index'])->name('genres.index');
Route::get('/genres/{genre:slug}', [GenreController::class, 'show'])->name('genres.show');

// View route (no controller); the page uses the legacy @extends layout for comparison.
Route::view('/about', 'about')->name('about');

/*
|--------------------------------------------------------------------------
| Authentication (hand-written controllers, no starter kit)
|--------------------------------------------------------------------------
| Route names login / verification.* are what the "auth" and "verified"
| middleware and the VerifyEmail notification redirect/link to.
*/

Route::middleware('guest')->group(function () {
    Route::get('/register', [Auth\RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [Auth\RegisteredUserController::class, 'store']);

    Route::get('/login', [Auth\AuthenticatedSessionController::class, 'create'])->name('login');
    // Named rate limiter "login" (AppServiceProvider): 5 attempts/min per email + IP.
    Route::post('/login', [Auth\AuthenticatedSessionController::class, 'store'])->middleware('throttle:login');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [Auth\AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/email/verify', [Auth\EmailVerificationController::class, 'notice'])->name('verification.notice');
    // "signed": the URL carries an HMAC signature + expiry; tampering => 403.
    Route::get('/email/verify/{id}/{hash}', [Auth\EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/email/verification-notification', [Auth\EmailVerificationController::class, 'send'])
        ->middleware('throttle:6,1')->name('verification.send');
});

/*
|--------------------------------------------------------------------------
| Staff area: managers and admins
|--------------------------------------------------------------------------
| Middleware run in order: auth (logged in) -> verified (email confirmed)
| -> role (manager or admin). Warehouse routes add warehouse.access.
*/

Route::prefix('manage')->name('manage.')->middleware(['auth', 'verified', 'role:manager,admin'])->group(function () {
    Route::get('/warehouses', [Manage\WarehouseController::class, 'index'])->name('warehouses.index');

    // {warehouse} binds by id; warehouse.access: a manager only gets their own one.
    Route::prefix('/warehouses/{warehouse}')->name('warehouses.')->middleware('warehouse.access')->group(function () {
        Route::get('/stocks', [Manage\StockController::class, 'index'])->name('stocks.index');
        Route::get('/movements', [Manage\MovementController::class, 'index'])->name('movements.index');

        // scopeBindings(): {stock} is looked up via $warehouse->stocks(), so
        // /warehouses/1/stocks/{id of a stock in warehouse 2}/edit is a 404 (IDOR protection);
        // can:update,stock then asks StockPolicy about the row itself.
        Route::scopeBindings()->middleware('can:update,stock')->group(function () {
            Route::get('/stocks/{stock}/edit', [Manage\StockController::class, 'edit'])->name('stocks.edit');
            Route::put('/stocks/{stock}', [Manage\StockController::class, 'update'])->name('stocks.update');
        });
    });
});

// Reports: invokable controller, the report name comes from ->defaults().
// A gate (view-reports) rather than role:manager,admin: the rule lives in one place
// and the same check drives the navigation link (@can('view-reports')).
Route::prefix('reports')->name('reports.')->middleware(['auth', 'verified', 'can:view-reports'])->group(function () {
    Route::get('/stock', ReportController::class)->defaults('report', 'stock')->name('stock');
    Route::get('/stock.csv', ReportController::class)->defaults('report', 'stock-csv')->name('stock-csv');
    Route::get('/top-authors', ReportController::class)->defaults('report', 'top-authors')->name('top-authors');
});

/*
|--------------------------------------------------------------------------
| Admin area
|--------------------------------------------------------------------------
*/

// Moderation works on pending reviews, which ApprovedScope hides from the
// default route model binding — resolve {review} without it.
Route::bind('review', fn (string $value) => Review::withoutGlobalScope(ApprovedScope::class)->findOrFail($value));

Route::prefix('admin')->name('admin.')->middleware(['auth', 'verified', 'role:admin'])->group(function () {
    Route::get('/', Admin\DashboardController::class)->name('dashboard');

    // withTrashed(['show']): soft-deleted books still open in the admin (public routes: 404).
    Route::resource('books', Admin\BookController::class)->withTrashed(['show']);
    Route::patch('/books/{book}/restore', [Admin\BookController::class, 'restore'])->name('books.restore')->withTrashed();
    Route::delete('/books/{book}/force', [Admin\BookController::class, 'forceDelete'])->name('books.force-delete')->withTrashed();

    Route::get('/reviews', [Admin\ReviewController::class, 'index'])->name('reviews.index');
    Route::patch('/reviews/{review}/approve', [Admin\ReviewController::class, 'approve'])->name('reviews.approve');
    Route::delete('/reviews/{review}', [Admin\ReviewController::class, 'destroy'])->name('reviews.destroy');
});

// Lab pages (stage demos): local/testing, or admins elsewhere (LabAccess).
Route::prefix('lab')->name('lab.')->middleware('lab')->group(function () {
    Route::get('/relations', LabRelationsController::class)->name('relations');
    Route::get('/n-plus-one', LabNPlusOneController::class)->name('n-plus-one');
    Route::get('/collections', LabCollectionsController::class)->name('collections');
    Route::get('/models', LabModelsController::class)->name('models');
});

// Any unmatched URL => our custom 404 page (instead of the framework's default).
Route::fallback(fn () => response()->view('errors.404', [], 404));
