<?php

namespace App\Models;

use App\Enums\MeetingStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Meeting extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'created_by',
        'title',
        'description',
        'date',
        'start_time',
        'end_time',
        'meeting_link',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'status' => MeetingStatus::class,
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'meeting_user')
            ->withPivot('status')
            ->withTimestamps();
    }

    public function transcripts(): HasMany
    {
        return $this->hasMany(MeetingTranscript::class);
    }

    public function aiSummary(): HasOne
    {
        return $this->hasOne(AiMeetingSummary::class);
    }

    // Scopes
    public function scopeForMonth($query, int $year, int $month)
    {
        return $query->whereYear('date', $year)->whereMonth('date', $month);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('created_by', $userId)
                ->orWhereHas('participants', fn ($sub) => $sub->where('users.id', $userId));
        });
    }

    // Accessors & Helpers
    public function getTimeSlotAttribute(): string
    {
        $start = $this->start_time ? substr((string) $this->start_time, 0, 5) : '';
        $end = $this->end_time ? substr((string) $this->end_time, 0, 5) : '';

        return ($start && $end) ? "{$start} - {$end}" : ($start ?: $end);
    }

    public function getDateFormattedAttribute(): string
    {
        if (! $this->date) {
            return '';
        }

        if ($this->date->isToday()) {
            return 'Today';
        }

        if ($this->date->isTomorrow()) {
            return 'Tomorrow';
        }

        return $this->date->format('M d');
    }

    public function getTranscriptsCountAttribute(): int
    {
        return (int) ($this->attributes['transcripts_count']
            ?? ($this->relationLoaded('transcripts') ? $this->transcripts->count() : $this->transcripts()->count()));
    }

    public function isParticipant(User|int $user): bool
    {
        $userId = $user instanceof User ? $user->id : $user;

        if ($this->created_by === $userId) {
            return true;
        }

        if ($this->relationLoaded('participants')) {
            return $this->participants->contains('id', $userId);
        }

        return $this->participants()->where('users.id', $userId)->exists();
    }

    public function getParticipantStatus(User|int $user): ?string
    {
        $userId = $user instanceof User ? $user->id : $user;
        $participant = $this->participants->first(fn ($p) => $p->id === $userId);

        return $participant?->pivot?->status;
    }

    public function getFormattedStartTimeAttribute(): string
    {
        return substr((string) $this->start_time, 0, 5);
    }

    public function getFormattedEndTimeAttribute(): string
    {
        return substr((string) $this->end_time, 0, 5);
    }

    public function getHasAiSummaryAttribute(): bool
    {
        return ! is_null($this->aiSummary);
    }

    public function getExtraParticipantsCountAttribute(): int
    {
        return max(0, $this->participants->count() - 3);
    }

    public function getTotalParticipantsCountAttribute(): int
    {
        return $this->participants->count();
    }

    public function getParticipantsPreviewAttribute()
    {
        return $this->participants->take(3)->map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'avatar_url' => $p->avatar_url,
            'status' => $p->pivot?->status,
        ]);
    }
}
