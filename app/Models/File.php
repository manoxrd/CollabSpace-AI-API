<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class File extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'folder_id',
        'task_id',
        'name',
        'path',
        'disk',
        'mime_type',
        'size',
        'downloads_count',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'downloads_count' => 'integer',
        ];
    }

    public function getSizeFormattedAttribute(): string
    {
        $bytes = max((int) $this->size, 0);
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min((int) $pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, 1).' '.$units[$pow];
    }

    public function getFileUrlAttribute(): ?string
    {
        return $this->path ? Storage::disk($this->disk ?? 'public')->url($this->path) : null;
    }

    public function getUploadedAtRelativeAttribute(): string
    {
        return $this->created_at ? $this->created_at->diffForHumans() : '';
    }

    public function getCardMetaAttribute(): string
    {
        return $this->size_formatted.' • '.$this->uploaded_at_relative;
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
