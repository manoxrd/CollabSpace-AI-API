<?php

namespace App\Models;

use App\Enums\ChatChannelType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ChatChannel extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'name',
        'type',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => ChatChannelType::class,
        ];
    }

    public function scopeDirect(Builder $query): Builder
    {
        return $query->where('type', ChatChannelType::Direct);
    }

    public function scopeProjectChannels(Builder $query): Builder
    {
        return $query->whereIn('type', [ChatChannelType::ProjectPublic, ChatChannelType::ProjectTeam]);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'chat_channel_user')
            ->withPivot('last_read_at', 'is_muted')
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(ChatMessage::class)->latestOfMany();
    }

    public function isDirect(): bool
    {
        return $this->type === ChatChannelType::Direct;
    }

    public function getOtherParticipant(User|int $user): ?User
    {
        $userId = $user instanceof User ? $user->id : $user;
        $members = $this->relationLoaded('members') ? $this->members : $this->members()->get();

        return $members->first(fn ($m) => $m->id !== $userId);
    }

    public function getUnreadCountFor(User|int $user): int
    {
        $userId = $user instanceof User ? $user->id : $user;
        $members = $this->relationLoaded('members') ? $this->members : $this->members()->get();
        $member = $members->first(fn ($m) => $m->id === $userId);
        $lastRead = $member?->pivot?->last_read_at;

        if ($this->relationLoaded('messages')) {
            return $this->messages
                ->where('user_id', '!=', $userId)
                ->when($lastRead, fn ($c) => $c->where('created_at', '>', $lastRead))
                ->count();
        }

        $query = $this->messages()->where('user_id', '!=', $userId);
        if ($lastRead) {
            $query->where('created_at', '>', $lastRead);
        }

        return $query->count();
    }

    public function isMutedFor(User|int $user): bool
    {
        $userId = $user instanceof User ? $user->id : $user;
        $member = $this->members->first(fn ($m) => $m->id === $userId);

        return (bool) ($member?->pivot?->is_muted ?? false);
    }

    public function getMembersCountAttribute(): int
    {
        return (int) ($this->attributes['members_count']
            ?? ($this->relationLoaded('members') ? $this->members->count() : $this->members()->count()));
    }
}
