<?php

namespace App\Models;

use App\Enums\ReportStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    use HasFactory;

    protected $fillable = [
        'generated_by',
        'project_id',
        'user_id',
        'report_type',
        'start_date',
        'end_date',
        'file_path',
        'metrics_data',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'metrics_data' => 'array',
            'status' => ReportStatus::class,
        ];
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getFileUrlAttribute(): ?string
    {
        return $this->file_path ? asset('storage/'.$this->file_path) : null;
    }

    public function getReportTypeLabelAttribute(): string
    {
        return match ($this->report_type) {
            'task_completion' => 'Task Completion',
            'worker_activity' => 'Worker Activity',
            'project_progress' => 'Project Progress',
            default => ucfirst(str_replace('_', ' ', (string) $this->report_type)),
        };
    }

    public function getFormattedCreatedAtAttribute(): string
    {
        return $this->created_at ? $this->created_at->format('M d, Y H:i') : '';
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForProject($query, int $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('report_type', $type);
    }

    public function scopeWithStatus($query, string|ReportStatus $status)
    {
        $value = $status instanceof ReportStatus ? $status->value : $status;

        return $query->where('status', $value);
    }
}
