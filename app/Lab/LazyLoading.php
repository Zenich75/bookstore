<?php

namespace App\Lab;

use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * Temporarily switch Eloquent guards off — for lab demos of what they prevent.
 */
final class LazyLoading
{
    /**
     * Run $callback with lazy loading allowed, then restore the previous setting.
     *
     * Note: the flag is copied onto each model when it is hydrated (Builder::hydrate),
     * so both the query and the code that touches relations must run inside.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function allowDuring(Closure $callback): mixed
    {
        $previous = Model::preventsLazyLoading();
        Model::preventLazyLoading(false);

        try {
            return $callback();
        } finally {
            Model::preventLazyLoading($previous);
        }
    }

    /**
     * Run $callback with "missing attribute" exceptions disabled (checked at access time).
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function allowMissingAttributesDuring(Closure $callback): mixed
    {
        $previous = Model::preventsAccessingMissingAttributes();
        Model::preventAccessingMissingAttributes(false);

        try {
            return $callback();
        } finally {
            Model::preventAccessingMissingAttributes($previous);
        }
    }
}
