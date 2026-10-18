<?php

namespace App\Http\Requests\Meetings;

use App\Enums\MeetingParticipantStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class RsvpMeetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', new Enum(MeetingParticipantStatus::class)],
        ];
    }
}
