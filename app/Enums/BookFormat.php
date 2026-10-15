<?php

namespace App\Enums;

enum BookFormat: string
{
    case Hardcover = 'hardcover';
    case Paperback = 'paperback';
    case Ebook = 'ebook';

    /**
     * Human-readable, translated name (lang/{en,uk}/enums.php).
     */
    public function label(): string
    {
        return __('enums.book_format.'.$this->value);
    }
}
