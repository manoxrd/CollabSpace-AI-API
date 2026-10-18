<?php

namespace App\Models;

use App\Enums\Priority;
use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'category',
        'description',
        'start_date',
        'deadline',
        'priority',
        'status',
        'progress_percentage',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'priority' => Priority::class,
            'status' => ProjectStatus::class,
            'progress_percentage' => 'float',
            'start_date' => 'date',
            'deadline' => 'date',
        ];
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ProjectStatus::Active);
    }

    public function scopeDone(Builder $query): Builder
    {
        return $query->where('status', ProjectStatus::Done);
    }

    public function recalculateProgress(): float
    {
        $totalTasks = $this->tasks()->count();
        if ($totalTasks === 0) {
            $this->update(['progress_percentage' => 0.00]);

            return 0.00;
        }

        $completedTasks = $this->tasks()->where('status', TaskStatus::Completed)->count();
        $progress = round(($completedTasks / $totalTasks) * 100, 1);

        $this->update(['progress_percentage' => $progress]);

        return $progress;
    }

    // Relationships
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'project_team')
            ->withPivot('assigned_at')
            ->withTimestamps();
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_user')
            ->withPivot('role', 'joined_at')
            ->withTimestamps();
    }

    public function hasMember(User|int $user): bool
    {
        $userId = $user instanceof User ? $user->id : $user;

        if ($this->created_by === $userId) {
            return true;
        }

        return $this->members()->where('users.id', $userId)->exists();
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function folders(): HasMany
    {
        return $this->hasMany(Folder::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(File::class);
    }

    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class);
    }

    public function chatChannels(): HasMany
    {
        return $this->hasMany(ChatChannel::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    public function getFilesCountAttribute(): int
    {
        return (int) ($this->attributes['files_count']
            ?? ($this->relationLoaded('files') ? $this->files->count() : $this->files()->count()));
    }

    public function getTotalTasksCountAttribute(): int
    {
        return (int) ($this->attributes['tasks_count']
            ?? ($this->relationLoaded('tasks') ? $this->tasks->count() : $this->tasks()->count()));
    }

    public function getCompletedTasksCountAttribute(): int
    {
        return (int) ($this->attributes['completed_tasks_count']
            ?? ($this->relationLoaded('tasks')
                ? $this->tasks->filter(fn ($t) => $t->isCompleted())->count()
                : $this->tasks()->whereIn('status', [TaskStatus::Completed->value, 'done'])->count()));
    }

    public function getComputedProgressAttribute(): float
    {
        if ($this->progress_percentage > 0) {
            return (float) $this->progress_percentage;
        }

        $total = $this->total_tasks_count;

        return $total > 0 ? (float) round(($this->completed_tasks_count / $total) * 100, 1) : 0.0;
    }
}
