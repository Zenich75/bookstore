<?php

namespace App\Lab\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Lab-only "users" model with mass assignment protection switched off, to show
 * what guarded = [] allows: User::create($request->all()) with role=admin in
 * the input creates an admin. Compare with App\Models\User, whose #[Fillable]
 * list does not contain role. Not used by the application itself.
 *
 * Deliberately not a subclass of User: #[Fillable] is inherited from the parent
 * class, and a non-empty fillable list wins over guarded = [].
 */
#[Table(name: 'users')]
class LabUser extends Model
{
    // Legacy property syntax on purpose (the modern equivalent is #[Unguarded]).
    // Every attribute becomes mass assignable — including role.
    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }
}
