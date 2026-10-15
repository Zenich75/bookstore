<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Book;
use App\Models\User;

/**
 * Found by auto-discovery: App\Models\Book => App\Policies\BookPolicy.
 *
 * Each ability names the admin check itself, so the policy reads (and unit-tests)
 * correctly on its own — Gate::before in AppServiceProvider is a shortcut, not
 * the only thing standing between a reader and the admin area.
 */
class BookPolicy
{
    /**
     * Create, edit, soft-delete books and their credits/genres/tags.
     */
    public function manage(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function viewTrashed(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function restore(User $user, Book $book): bool
    {
        return $user->role === UserRole::Admin && $book->trashed();
    }

    public function forceDelete(User $user, Book $book): bool
    {
        return $user->role === UserRole::Admin && $book->trashed();
    }
}
