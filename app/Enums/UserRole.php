<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case ProjectManager = 'project_manager';
    case TeamMember = 'team_member';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::ProjectManager => 'Project Manager',
            self::TeamMember => 'Team Member',
        };
    }
}
