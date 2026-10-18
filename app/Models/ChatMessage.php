<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'chat_channel_id',
        'user_id',
        'parent_id',
        'message',
        'is_edited',
        'edited_at',
    ];

    protected function casts(): array
    {
        return [
            'is_edited' => 'boolean',
            'edited_at' => 'datetime',
        ];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(ChatChannel::class, 'chat_channel_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'parent_id');
    }

    public function attachments(): BelongsToMany
    {
        return $this->belongsToMany(File::class, 'chat_message_attachments');
    }

    public function isReply(): bool
    {
        return ! is_null($this->parent_id);
    }

    public function getRepliesCountAttribute(): int
    {
        return (int) ($this->attributes['replies_count']
            ?? ($this->relationLoaded('replies') ? $this->replies->count() : $this->replies()->count()));
    }
}
