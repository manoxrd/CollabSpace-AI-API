<?php

namespace App\Events;

use App\Http\Resources\Meetings\MeetingTranscriptResource;
use App\Models\MeetingTranscript;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MeetingTranscriptAdded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public MeetingTranscript $transcript) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('meeting.'.$this->transcript->meeting_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'meeting.transcript.added';
    }

    public function broadcastWith(): array
    {
        return [
            'transcript' => (new MeetingTranscriptResource($this->transcript->load('user')))->resolve(),
        ];
    }
}
