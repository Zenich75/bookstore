<?php

namespace App\Models;

use Database\Factories\ProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'avatar_path', 'phone', 'bio'])]
#[Hidden(['phone'])]
class Profile extends Model
{
    /** @use HasFactory<ProfileFactory> */
    use HasFactory;
}
