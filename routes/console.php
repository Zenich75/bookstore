<?php

use App\Jobs\CheckLowStock;
use App\Models\StockMovement;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduler
|--------------------------------------------------------------------------
| Run by `schedule:work` (Sail service "scheduler") or by cron every minute:
| * * * * * php artisan schedule:run. Inspect with `php artisan schedule:list`.
*/

// Queues the job (fan-out: one CheckLowStock per warehouse); the worker sends the digests.
// withoutOverlapping(): skip if the previous run still holds the lock.
// onOneServer(): with several app servers, only one of them dispatches (cache lock;
// needs a shared cache store — database here).
Schedule::job(new CheckLowStock)
    ->dailyAt('07:00')
    ->withoutOverlapping()
    ->onOneServer();

// Deletes stock movements older than config('warehouse.movements_retention_days')
// (StockMovement::prunable(), MassPrunable => one DELETE query).
Schedule::command('model:prune', ['--model' => [StockMovement::class]])
    ->dailyAt('03:00')
    ->onOneServer();
