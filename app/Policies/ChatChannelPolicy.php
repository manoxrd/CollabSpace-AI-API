<?php

namespace App\Policies;

use App\Enums\ChatChannelType;
use App\Models\ChatChannel;
use App\Models\User;

class ChatChannelPolicy
{
    /**
     * Determine whether the user can view the channel and its messages.
     */
    public function view(User $user, ChatChannel $channel): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($channel->type === ChatChannelType::Direct) {
            return $channel->members()->where('users.id', $user->id)->exists();
        }

        if ($channel->project_id && $channel->project) {
            return $channel->project->hasMember($user->id);
        }

        return false;
    }

    /**
     * Determine whether the user can send messages to this channel.
     */
    public function sendMessage(User $user, ChatChannel $channel): bool
    {
        return $this->view($user, $channel);
    }

    /**
     * Determine whether the user can update or delete this channel.
     */
    public function manage(User $user, ChatChannel $channel): bool
    {
        if ($channel->type === ChatChannelType::Direct) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if ($channel->project_id && $user->isManagerOfProject($channel->project_id)) {
            return true;
        }

        return $channel->created_by === $user->id;
    }
}
