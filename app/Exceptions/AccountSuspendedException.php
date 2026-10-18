<?php

namespace App\Exceptions;

use App\Traits\ApiResponse;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountSuspendedException extends Exception
{
    use ApiResponse;

    public function render(Request $request): JsonResponse
    {
        return $this->error([], 'Your account has been suspended. Please contact administration.', 403);
    }
}
