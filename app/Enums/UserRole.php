<?php

namespace App\Enums;

enum UserRole: string
{
    case Reader = 'reader';
    case Manager = 'manager';
    case Admin = 'admin';

    /**
     * Human-readable, translated name (lang/{en,uk}/enums.php).
     */
    public function label(): string
    {
        return __('enums.user_role.'.$this->value);
    }
}
