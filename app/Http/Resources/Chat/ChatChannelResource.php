<?php

namespace App\Http\Resources\Chat;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatChannelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $authUserId = auth('api')->id();
        $latestMessage = $this->latestMessage;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type?->value ?? $this->type,
            'type_label' => $this->type?->label(),
            'project_id' => $this->project_id,
            'project_name' => $this->project?->name,
            'created_by' => $this->created_by,
            'members_count' => $this->members_count,
            'last_message' => $latestMessage ? [
                'id' => $latestMessage->id,
                'message' => $latestMessage->message,
                'sender_id' => $latestMessage->user_id,
                'sender_name' => $latestMessage->user?->name,
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
