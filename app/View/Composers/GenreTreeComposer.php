<?php

namespace App\View\Composers;

use App\Models\Genre;
use Illuminate\View\View;

/**
 * Shares the root genre tree with the catalog sidebar, so the controller
 * doesn't have to pass it. Registered in AppServiceProvider.
 */
class GenreTreeComposer
{
    public function compose(View $view): void
    {
        // Children are eager-loaded: <x-genre-tree> renders them recursively.
        $tree = Genre::whereNull('parent_id')
            ->with(['children' => fn ($query) => $query->orderBy('name')])
            ->orderBy('name')
            ->get();

        $view->with('genreTree', $tree);
    }
}
