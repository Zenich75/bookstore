<?php

namespace App\Enums;

enum ImageType: string
{
    case Cover = 'cover';
    case Photo = 'photo';
    case Gallery = 'gallery';

    /**
     * Human-readable, translated name (lang/{en,uk}/enums.php).
     */
    public function label(): string
    {
        return __('enums.image_type.'.$this->value);
    }
}
