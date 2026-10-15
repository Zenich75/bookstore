<?php

namespace App\Enums;

enum AuthorRole: string
{
    case Author = 'author';
    case CoAuthor = 'co_author';
    case Translator = 'translator';

    /**
     * Human-readable, translated name (lang/{en,uk}/enums.php).
     */
    public function label(): string
    {
        return __('enums.author_role.'.$this->value);
    }
}
