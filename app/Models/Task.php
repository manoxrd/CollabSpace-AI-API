<?php

namespace App\Models;

use App\Enums\Priority;
use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'project_id',
        'team_id',
        'created_by',
        'reviewer_id',
        'name',
        'description',
        'status',
        'priority',
        'start_date',
        'due_date',
        'completed_at',
        'progress_percentage',
    ];

    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'priority' => Priority::class,
            'start_date' => 'date',
            'due_date' => 'date',
            'completed_at' => 'datetime',
            'progress_percentage' => 'float',
        ];
    }

    // Scopes for Kanban columns
    public function scopeTodo(Builder $query): Builder
    {
        return $query->where('status', TaskStatus::Todo);
    }

    public function scopeInProgress(Builder $query): Builder
    {
        return $query->where('status', TaskStatus::InProgress);
    }

    public function scopeInReview(Builder $query): Builder
    {
        return $query->where('status', TaskStatus::InReview);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', TaskStatus::Completed);
    }

    // Accessors & State Helpers
    public function isCompleted(): bool
    {
        $statusVal = $this->status instanceof TaskStatus ? $this->status->value : $this->status;

        return $statusVal === TaskStatus::Completed->value;
    }

    public function getDueDateFormattedAttribute(): ?string
    {
        if (! $this->due_date) {
            return null;
        }

        $now = now()->startOfDay();
        $dueDay = $this->due_date->copy()->startOfDay();

        if ($dueDay->isSameDay($now)) {
            return 'due today';
        }

        if ($dueDay->isPast()) {
            return 'overdue';
        }

        return 'due '.$this->due_date->format('M d');
    }

    public function getIsDueTodayAttribute(): bool
    {
        if (! $this->due_date) {
            return false;
        }

        return $this->due_date->copy()->startOfDay()->isSameDay(now()->startOfDay());
    }

    public function getIsOverdueAttribute(): bool
    {
        if (! $this->due_date) {
            return false;
        }

        return $this->due_date->copy()->startOfDay()->isPast() && ! $this->isCompleted();
    }

    // Relationships
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_user')
            ->withPivot('assigned_at')
            ->withTimestamps();
    }

    public function activities(): HasMany
    {
        return $this->hasMany(TaskActivity::class)->orderByDesc('created_at');
    }

    public function files(): HasMany
    {
        return $this->hasMany(File::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class)->orderByDesc('created_at');
    }

    public function timeLogs(): HasMany
    {
        return $this->hasMany(TaskTimeLog::class)->orderByDesc('date');
    }

    public function getCommentsCountAttribute(): int
    {
        return (int) ($this->attributes['comments_count']
            ?? ($this->relationLoaded('comments') ? $this->comments->count() : $this->comments()->count()));
    }

    public function getFilesCountAttribute(): int
    {
        return (int) ($this->attributes['files_count']
            ?? ($this->relationLoaded('files') ? $this->files->count() : $this->files()->count()));
    }
}
