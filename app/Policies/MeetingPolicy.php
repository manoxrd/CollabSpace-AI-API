<?php

namespace App\Policies;

use App\Models\Meeting;
use App\Models\User;

class MeetingPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Meeting $meeting): bool
    {
        if ($user->isAdmin() || $meeting->created_by === $user->id) {
            return true;
        }

        if ($meeting->isParticipant($user->id)) {
            return true;
        }

        return (bool) ($meeting->project && $meeting->project->hasMember($user->id));
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Meeting $meeting): bool
    {
        if ($user->isAdmin() || $meeting->created_by === $user->id) {
            return true;
        }

        return $user->isManagerOfProject($meeting->project_id);
    }

    public function delete(User $user, Meeting $meeting): bool
    {
        if ($user->isAdmin() || $meeting->created_by === $user->id) {
            return true;
        }

        return $user->isManagerOfProject($meeting->project_id);
    }

    public function rsvp(User $user, Meeting $meeting): bool
    {
        return $this->view($user, $meeting);
    }

    public function addTranscript(User $user, Meeting $meeting): bool
    {
        return $this->view($user, $meeting);
    }

    public function generateAiSummary(User $user, Meeting $meeting): bool
    {
        return $this->view($user, $meeting);
    }
}
