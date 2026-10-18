<?php

namespace App\Http\Requests\Meetings;

use Illuminate\Foundation\Http\FormRequest;

class StoreTranscriptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'transcript_text' => ['required', 'string'],
            'speaker_name' => ['nullable', 'string', 'max:100'],
            'timestamp_offset' => ['nullable', 'string', 'max:20'],
            'spoken_at' => ['nullable', 'date'],
        ];
    }
}
