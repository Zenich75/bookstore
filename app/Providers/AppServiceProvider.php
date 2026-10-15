<?php

namespace App\Providers;

use App\Models\Author;
use App\Models\Book;
use App\View\Composers\GenreTreeComposer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Number;
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

        // What a lazy-loading violation does: fail loudly while developing/testing,
        // but only log in production — an N+1 should not take the site down.
        Model::handleLazyLoadingViolationUsing(function (Model $model, string $relation, LazyLoadingViolationException $e) {
            // Same exemption as the framework default: new models have nothing to eager-load.
            if (! $model->exists || $model->wasRecentlyCreated) {
                return;
            }

            if (app()->environment('local', 'testing')) {
                throw $e;
            }

            Log::warning('N+1 query: lazy loading ['.$relation.'] on ['.$model::class.'].', [
                'model' => $model::class,
                'key' => $model->getKey(),
                'relation' => $relation,
                'url' => app()->runningInConsole() ? null : request()->fullUrl(),
            ]);
        });

        // Store short aliases in *_type morph columns instead of class names, and
        // throw if a model without an alias is used in a polymorphic relation.
        // See docs/02-relations.md ("Morph map").
        Relation::enforceMorphMap([
            'book' => Book::class,
            'author' => Author::class,
        ]);

        // Number::format()/fileSize() follow the app locale (uk: "1 234,5"). Carbon picks
        // up the locale from the translator automatically.
        Number::useLocale(app()->getLocale());

        // View composer: runs whenever the catalog sidebar partial is rendered.
        View::composer('books.partials.sidebar', GenreTreeComposer::class);
    }
}
