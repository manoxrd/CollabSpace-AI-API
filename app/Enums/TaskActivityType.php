<?php

namespace App\Enums;

enum TaskActivityType: string
{
    case Comment = 'comment';
    case StatusChange = 'status_change';
    case Blocker = 'blocker';
    case ProgressUpdate = 'progress_update';

    public function label(): string
    {
        return match ($this) {
            self::Comment => 'Comment',
            self::StatusChange => 'Status Change',
            self::Blocker => 'Blocker',
            self::ProgressUpdate => 'Progress Update',
        };
    }
}
