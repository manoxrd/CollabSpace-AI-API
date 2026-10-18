<?php

namespace App\Exceptions;

use App\Traits\ApiResponse;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvalidCredentialsException extends Exception
{
    use ApiResponse;

    public function render(Request $request): JsonResponse
    {
        return $this->error([], 'Invalid email or password.', 401);
    }
}
