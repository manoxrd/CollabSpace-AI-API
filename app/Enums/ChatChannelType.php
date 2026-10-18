<?php

namespace App\Enums;

enum ChatChannelType: string
{
    case Direct = 'direct';               // Inbox direct 1-on-1 chat
    case ProjectPublic = 'project_public'; // Project public channel
    case ProjectTeam = 'project_team';     // Project department channel (Frontend, Backend, etc.)

    public function label(): string
    {
        return match ($this) {
            self::Direct => 'Direct Message',
            self::ProjectPublic => 'Public Channel',
            self::ProjectTeam => 'Team Channel',
        };
    }
}
