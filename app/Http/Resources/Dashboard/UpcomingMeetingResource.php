<?php

namespace App\Http\Resources\Dashboard;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UpcomingMeetingResource extends JsonResource
{
    /**
     * Transform the meeting into an array for dashboard upcoming meetings.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $dateFormatted = 'Scheduled';
        if ($this->date) {
            if ($this->date->isToday()) {
                $dateFormatted = 'Today';
            } elseif ($this->date->isTomorrow()) {
                $dateFormatted = 'Tomorrow';
            } else {
                $dateFormatted = $this->date->format('M d'); // e.g. "Dec 12"
            }
        }

        $startTime = $this->start_time ? substr((string) $this->start_time, 0, 5) : '';
        $endTime = $this->end_time ? substr((string) $this->end_time, 0, 5) : '';
        $timeSlot = "{$startTime} - {$endTime}";

        $participants = $this->participants ?? collect();
        $displayParticipants = $participants->take(3)->map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'avatar_url' => $p->avatar_url,
        ]);
        $extraCount = max(0, $participants->count() - 3);

        return [
            'id' => $this->id,
            'title' => $this->title,
            'date_formatted' => $dateFormatted,
            'time_slot' => $timeSlot,
            'meeting_link' => $this->meeting_link,
            'status' => $this->status?->value ?? $this->status,
            'participants' => $displayParticipants,
            'extra_participants_count' => $extraCount, // e.g. "+3" overflow badge
            'total_participants_count' => $participants->count(),
        ];
    }
}
