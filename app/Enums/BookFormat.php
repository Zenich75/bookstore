<?php

namespace App\Enums;

enum BookFormat: string
{
    case Hardcover = 'hardcover';
    case Paperback = 'paperback';
    case Ebook = 'ebook';
}
