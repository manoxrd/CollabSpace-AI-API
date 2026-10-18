<?php

namespace App\Models;

use App\Enums\AiMessageRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'ai_conversation_id',
        'role',
        'content',
        'feedback',
        'insights',
        'citations',
    ];

    protected function casts(): array
    {
        return [
            'role' => AiMessageRole::class,
            'insights' => 'array',
            'citations' => 'array',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'ai_conversation_id');
    }

    public function isAssistant(): bool
    {
        return $this->role === AiMessageRole::Assistant;
    }

    public function isUser(): bool
    {
        return $this->role === AiMessageRole::User;
    }
}
