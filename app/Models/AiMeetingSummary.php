<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiMeetingSummary extends Model
{
    use HasFactory;

    protected $fillable = [
        'meeting_id',
        'summary',
        'key_points',
        'decisions_made',
        'action_items',
        'assigned_tasks',
        'risks',
        'next_steps',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'key_points' => 'array',
            'decisions_made' => 'array',
            'action_items' => 'array',
            'assigned_tasks' => 'array',
            'risks' => 'array',
            'next_steps' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }
}
