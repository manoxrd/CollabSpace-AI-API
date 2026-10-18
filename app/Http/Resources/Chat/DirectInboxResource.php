<?php

namespace App\Http\Resources\Chat;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DirectInboxResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $authUserId = auth('api')->id();
        $otherUser = $this->getOtherParticipant($authUserId);
        $latestMessage = $this->latestMessage;

        return [
            'id' => $this->id,
            'channel_type' => $this->type?->value ?? $this->type,
            'participant' => $otherUser ? [
                'id' => $otherUser->id,
                'name' => $otherUser->name,
                'email' => $otherUser->email,
                'job_title' => $otherUser->job_title,
                'avatar_url' => $otherUser->avatar_url,
                'availability_status' => $otherUser->availability_status?->value ?? $otherUser->availability_status,
            ] : null,
            'last_message' => $latestMessage ? [
                'id' => $latestMessage->id,
                'message' => $latestMessage->message,
                'sender_id' => $latestMessage->user_id,
                'is_sender' => $latestMessage->user_id === $authUserId,
                'created_at' => $latestMessage->created_at?->toISOString(),
                'time_ago' => $latestMessage->created_at?->diffForHumans(),
            ] : null,
            'unread_count' => $this->getUnreadCountFor($authUserId),
            'is_muted' => $this->isMutedFor($authUserId),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
