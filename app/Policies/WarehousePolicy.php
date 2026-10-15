<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\Warehouse;

class WarehousePolicy
{
    /**
     * Same rule as the EnsureWarehouseAccess middleware, per resource (@can in views).
     */
    public function view(User $user, Warehouse $warehouse): bool
    {
        return $user->role === UserRole::Admin
            || ($user->role === UserRole::Manager && $user->warehouse_id === $warehouse->id);
    }
}
