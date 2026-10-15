<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Toggle with MODEL_STRICT in .env (see config/app.php and docs/00-setup.md).
        Model::shouldBeStrict((bool) config('app.model_strict'));
    }
}
