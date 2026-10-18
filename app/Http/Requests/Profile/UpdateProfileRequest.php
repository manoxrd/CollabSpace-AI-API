<?php

namespace App\Http\Requests\Profile;

use App\Enums\UserAvailability;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation rules for the profile and settings update request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userId = $this->user('api')?->id ?? auth('api')->id();

        return [
            // Personal Info
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'availability_status' => ['sometimes', 'required', Rule::enum(UserAvailability::class)],

            // Professional Details
            'job_title' => ['nullable', 'string', 'max:100'],
            'years_of_experience' => ['nullable', 'integer', 'min:0', 'max:70'],
            'current_team_project' => ['nullable', 'string', 'max:150'],
            'about' => ['nullable', 'string', 'max:2000'],

            // Avatar file upload only
            'avatar' => ['nullable', 'image', 'max:5120'], // 5MB max
        ];
    }
}
