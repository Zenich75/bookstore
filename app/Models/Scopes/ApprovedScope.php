<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global scope: every Review query (including relations, withCount, whereHas)
 * sees approved reviews only. Moderation code opts out with
 * ->withoutGlobalScope(ApprovedScope::class).
 */
class ApprovedScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        // Qualified column: the scope also runs inside joins and has-many-through queries.
        $builder->where($model->qualifyColumn('is_approved'), true);
    }
}
