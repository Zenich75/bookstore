<?php

namespace App\Enums;

enum UserRole: string
{
    case Reader = 'reader';
    case Manager = 'manager';
    case Admin = 'admin';
}
