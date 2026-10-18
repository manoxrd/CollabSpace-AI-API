<?php

namespace App\Policies;

use App\Models\AiMessage;
use App\Models\User;

class AiMessagePolicy
{
    public function feedback(User $user, AiMessage $message): bool
    {
        return $message->conversation->user_id === $user->id || $user->isAdmin();
    }
}
