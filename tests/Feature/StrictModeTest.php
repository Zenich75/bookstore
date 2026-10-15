<?php

use App\Providers\AppServiceProvider;
use Illuminate\Database\Eloquent\Model;

test('eloquent strict mode is enabled by default', function () {
    expect(config('app.model_strict'))->toBeTrue()
        ->and(Model::preventsLazyLoading())->toBeTrue()
        ->and(Model::preventsSilentlyDiscardingAttributes())->toBeTrue()
        ->and(Model::preventsAccessingMissingAttributes())->toBeTrue();
});

test('eloquent strict mode can be disabled via config', function () {
    config(['app.model_strict' => false]);
    (new AppServiceProvider($this->app))->boot();

    expect(Model::preventsLazyLoading())->toBeFalse();
});
