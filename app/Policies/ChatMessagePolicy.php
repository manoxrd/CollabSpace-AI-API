<?php

namespace App\Policies;

use App\Models\ChatMessage;
use App\Models\User;

class ChatMessagePolicy
{
    /**
     * Determine whether the user can update the message.
     */
    public function update(User $user, ChatMessage $message): bool
    {
        return $message->user_id === $user->id;
    }

    /**
     * Determine whether the user can delete the message.
     */
    public function delete(User $user, ChatMessage $message): bool
    {
        if ($message->user_id === $user->id) {
            return true;
        }

        if ($user->isAdmin()) {
            return true;
        }

        $channel = $message->channel;
        if ($channel && $channel->project_id && $user->isManagerOfProject($channel->project_id)) {
            return true;
        }

        return false;
    }
}
