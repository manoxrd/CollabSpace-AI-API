<?php

namespace App\Models;

use App\Enums\TaskStatus;
use App\Enums\UserAvailability;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     */
    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     */
    public function getJWTCustomClaims(): array
    {
        return [
            'role' => $this->role instanceof UserRole ? $this->role->value : $this->role,
            'name' => $this->name,
            'email' => $this->email,
        ];
    }

    protected $fillable = [
        'name',
        'email',
        'password',
        'google_id',
        'role',
        'job_title',
        'years_of_experience',
        'phone',
        'avatar_url',
        'availability_status',
        'about',
        'hours_worked',
        'current_team_project',
        'status',
        'joined_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'availability_status' => UserAvailability::class,
            'joined_at' => 'date',
            'years_of_experience' => 'integer',
            'hours_worked' => 'integer',
        ];
    }

    // Relationships

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_user')
            ->withPivot('role_in_team', 'joined_at')
            ->withTimestamps();
    }

    public function ledTeams(): HasMany
    {
        return $this->hasMany(Team::class, 'leader_id');
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_user')
            ->withPivot('role', 'joined_at')
            ->withTimestamps();
    }

    public function createdProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'created_by');
    }

    public function assignedTasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_user')
            ->withPivot('assigned_at')
            ->withTimestamps();
    }

    public function createdTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'created_by');
    }

    public function meetings(): BelongsToMany
    {
        return $this->belongsToMany(Meeting::class, 'meeting_user')
            ->withPivot('status')
            ->withTimestamps();
    }

    public function chatChannels(): BelongsToMany
    {
        return $this->belongsToMany(ChatChannel::class, 'chat_channel_user')
            ->withPivot('last_read_at', 'is_muted')
            ->withTimestamps();
    }

    public function chatMessages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    public function uploadedFiles(): HasMany
    {
        return $this->hasMany(File::class, 'uploaded_by');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(UserActivity::class)->orderByDesc('created_at');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class, 'user_id');
    }

    public function taskComments(): HasMany
    {
        return $this->hasMany(TaskComment::class);
    }

    public function timeLogs(): HasMany
    {
        return $this->hasMany(TaskTimeLog::class);
    }

    public function refreshTokens(): HasMany
    {
        return $this->hasMany(RefreshToken::class);
    }

    // Role and permission helpers
    public function isAdmin(): bool
    {
        return ($this->role?->value ?? $this->role) === UserRole::Admin->value;
    }

    public function isProjectManager(): bool
    {
        return in_array($this->role?->value ?? $this->role, [
            UserRole::ProjectManager->value,
            UserRole::Admin->value,
        ]);
    }

    public function isManagerOfProject(?int $projectId = null): bool
    {
        if ($this->isAdmin() || $this->isProjectManager()) {
            return true;
        }

        if (! $projectId) {
            return false;
        }

        return $this->projects()
            ->where('projects.id', $projectId)
            ->wherePivot('role', 'manager')
            ->exists();
    }

    public function getAssignedTasksCountAttribute(): int
    {
        return (int) ($this->attributes['assigned_tasks_count']
            ?? ($this->relationLoaded('assignedTasks')
                ? $this->assignedTasks->count()
                : $this->assignedTasks()->count()));
    }

    public function getCompletedAssignedTasksCountAttribute(): int
    {
        return (int) ($this->attributes['completed_assigned_tasks_count']
            ?? ($this->relationLoaded('assignedTasks')
                ? $this->assignedTasks->filter(fn ($t) => $t->isCompleted())->count()
                : $this->assignedTasks()->whereIn('status', [TaskStatus::Completed->value, 'done'])->count()));
    }

    public function getTaskCompletionRateAttribute(): int
    {
        $total = $this->assigned_tasks_count;

        return $total > 0 ? (int) round(($this->completed_assigned_tasks_count / $total) * 100) : 0;
    }

    public function getRecentActivitiesAttribute(): Collection
    {
        return $this->relationLoaded('activities')
            ? $this->activities->take(5)
            : $this->activities()->take(5)->get();
    }
}
