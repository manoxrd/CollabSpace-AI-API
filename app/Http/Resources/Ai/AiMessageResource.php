<?php

namespace App\Http\Resources\Ai;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AiMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ai_conversation_id' => $this->ai_conversation_id,
            'role' => $this->role->value,
            'content' => $this->content,
            'feedback' => $this->feedback,
            'insights' => $this->insights,
            'citations' => $this->citations,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
