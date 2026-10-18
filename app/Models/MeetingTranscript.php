<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingTranscript extends Model
{
    use HasFactory;

    protected $fillable = [
        'meeting_id',
        'user_id',
        'speaker_name',
        'timestamp_offset',
        'transcript_text',
        'spoken_at',
    ];

    protected function casts(): array
    {
        return [
            'spoken_at' => 'datetime',
        ];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
