<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Stock;
use App\Models\User;

class StockPolicy
{
    /**
     * Admin: any stock row. Manager: rows of the warehouse they work at.
     */
    public function update(User $user, Stock $stock): bool
    {
        return $user->role === UserRole::Admin
            || ($user->role === UserRole::Manager && $user->warehouse_id === $stock->warehouse_id);
    }
}
