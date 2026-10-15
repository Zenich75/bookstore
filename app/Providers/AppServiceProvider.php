<?php

namespace App\Providers;

use App\Models\Author;
use App\Models\Book;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
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

        // Store short aliases in *_type morph columns instead of class names, and
        // throw if a model without an alias is used in a polymorphic relation.
        // See docs/02-relations.md ("Morph map").
        Relation::enforceMorphMap([
            'book' => Book::class,
            'author' => Author::class,
        ]);
    }
}
