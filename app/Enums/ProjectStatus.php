<?php

namespace App\Enums;

enum ProjectStatus: string
{
    case Active = 'active';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Done => 'Done',
        };
    }
}
