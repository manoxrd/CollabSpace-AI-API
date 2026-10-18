<?php

namespace App\Exceptions;

use App\Traits\ApiResponse;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvalidRefreshTokenException extends Exception
{
    use ApiResponse;

    public function render(Request $request): JsonResponse
    {
        return $this->error([], 'Invalid or expired refresh token.', 401);
    }
}
