<?php

namespace App\Policies;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;

class AiConversationPolicy
{
    public function view(User $user, AiConversation $conversation): bool
    {
        return $conversation->user_id === $user->id || $user->isAdmin();
    }

    public function delete(User $user, AiConversation $conversation): bool
    {
        return $conversation->user_id === $user->id || $user->isAdmin();
    }

    public function sendMessage(User $user, AiConversation $conversation): bool
    {
        return $conversation->user_id === $user->id || $user->isAdmin();
    }

    public function feedback(User $user, AiMessage $message): bool
    {
        return $message->conversation->user_id === $user->id || $user->isAdmin();
    }
}
