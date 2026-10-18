<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ProjectReviewerRule implements ValidationRule
{
    /**
     * @param  int|Closure|null  $projectId  Project ID or closure returning it
     */
    public function __construct(protected mixed $projectId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value) {
            return;
        }

        $user = User::find($value);
        if (! $user) {
            return;
        }

        $projectId = is_callable($this->projectId) ? ($this->projectId)() : $this->projectId;

        if (! $user->isManagerOfProject($projectId ? (int) $projectId : null)) {
            $fail('The designated reviewer must be a project manager.');
        }
    }
}
