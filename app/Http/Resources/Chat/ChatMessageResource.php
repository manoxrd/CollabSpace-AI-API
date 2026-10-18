<?php

namespace App\Http\Resources\Chat;

use App\Http\Resources\FileResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $authUserId = auth('api')->id();

        return [
            'id' => $this->id,
            'chat_channel_id' => $this->chat_channel_id,
            'message' => $this->message,
            'parent_id' => $this->parent_id,
            'is_reply' => ! is_null($this->parent_id),
            'parent_message' => $this->parent ? [
                'id' => $this->parent->id,
                'message' => $this->parent->message,
                'sender_name' => $this->parent->user?->name,
            ] : null,
            'replies_count' => $this->replies_count,
            'is_edited' => (bool) $this->is_edited,
            'edited_at' => $this->edited_at?->toISOString(),
            'sender' => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
                'avatar_url' => $this->user?->avatar_url,
                'job_title' => $this->user?->job_title,
                'availability_status' => $this->user?->availability_status?->value ?? $this->user?->availability_status,
                'is_me' => $this->user_id === $authUserId,
            ],
            'attachments' => FileResource::collection($this->attachments),
            'created_at' => $this->created_at?->toISOString(),
            'created_at_formatted' => $this->created_at?->format('H:i'), // e.g. "14:02"
            'time_ago' => $this->created_at?->diffForHumans(),
        ];
    }
}
