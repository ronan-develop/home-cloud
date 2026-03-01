<?php

declare(strict_types=1);

namespace App\Enum;

enum FolderMediaType: string
{
    case General  = 'general';
    case Photo    = 'photo';
    case Video    = 'video';
    case Document = 'document';
    case Music    = 'music';
    case Other    = 'other';
}
