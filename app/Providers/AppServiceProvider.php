<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Http\Middleware\LogSlowRequests;
use App\Models\Author;
use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use App\View\Composers\GenreTreeComposer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Number;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Abilities whose policy checks the state of the data, not the user's privileges
     * (only a trashed book can be restored/force-deleted, only a pending review
     * approved). The admin bypass in Gate::before must not skip these checks.
     */
    private const STATE_RULE_ABILITIES = ['restore', 'forceDelete', 'approve'];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Terminable middleware is resolved again for terminate(); a singleton makes
        // that the same instance, so terminate() sees the start time set in handle().
        $this->app->singleton(LogSlowRequests::class);
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

        $this->configureAuthorization();
        $this->configureRateLimiting();

        // @role('manager', 'admin') ... @endrole in Blade.
        Blade::if('role', fn (string ...$roles) => in_array(auth()->user()?->role?->value, $roles, true));
    }

    private function configureAuthorization(): void
    {
        // Runs before every gate and policy check. Return true to allow, null to let
        // the gate/policy decide — never false: that would deny admins things the
        // policy allows (and hide bugs, because the policy never runs).
        Gate::before(function (User $user, string $ability, array $arguments) {
            if ($user->role !== UserRole::Admin) {
                return null;
            }

            // Data rules are not privileges: "one review per book" must hold for
            // admins too, so this check is left to ReviewPolicy::create(). Same for
            // state rules — without this, an admin could force-delete a book that is
            // not in the trash (caught by BookAdminTest).
            if (in_array($ability, self::STATE_RULE_ABILITIES, true)
                || ($ability === 'create' && ($arguments[0] ?? null) === Review::class)) {
                return null;
            }

            return true;
        });

        // A gate: an ability that is not about one model (policies are per model).
        Gate::define('view-reports', fn (User $user) => in_array($user->role, [UserRole::Manager, UserRole::Admin], true));

        // Policies (App\Policies\{Model}Policy) are found by auto-discovery.
    }

    private function configureRateLimiting(): void
    {
        // 5 attempts per minute per email + IP: guessing one account's password from
        // one address is throttled, without locking out everyone behind the same NAT.
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)
            ->by(mb_strtolower((string) $request->input('email')).'|'.$request->ip())
            ->response(fn (Request $request, array $headers) => back()
                ->withInput($request->only('email', 'remember'))
                ->withErrors(['email' => __('auth.throttle', ['seconds' => $headers['Retry-After'] ?? 60])])));

        // Used by the review form in stage 8.
        RateLimiter::for('reviews', fn (Request $request) => Limit::perHour(10)->by($request->user()?->id ?: $request->ip()));
    }
}
