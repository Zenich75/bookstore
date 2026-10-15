<?php

namespace App\Enums;

enum AuthorRole: string
{
    case Author = 'author';
    case CoAuthor = 'co_author';
    case Translator = 'translator';
}
