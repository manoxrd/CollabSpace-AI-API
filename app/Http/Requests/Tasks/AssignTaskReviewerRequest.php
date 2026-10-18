<?php

namespace App\Http\Requests\Tasks;

use App\Models\Task;
use App\Rules\ProjectReviewerRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class AssignTaskReviewerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $task = $this->route('task');
        $projectId = $task instanceof Task ? $task->project_id : null;

        return [
            'reviewer_id' => [
                'nullable',
                'integer',
                'exists:users,id',
                new ProjectReviewerRule($projectId),
            ],
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validation error',
            'errors' => $validator->errors(),
        ], 422));
    }
}
