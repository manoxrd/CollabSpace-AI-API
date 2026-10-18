<?php

namespace App\Http\Controllers\Api;

use App\DTOs\AuthResult;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\GoogleAuthRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Laravel\Socialite\Facades\Socialite;

class GoogleController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected AuthService $authService
    ) {}

    /**
     * Authenticate or register a user via the Google token provided by the frontend.
     */
    public function callback(GoogleAuthRequest $request): JsonResponse
    {
        try {
            $googleUser = Socialite::driver('google')->userFromToken($request->validated('token'));
        } catch (\Throwable $e) {
            return $this->error([], 'Invalid Google token.', 401);
        }

        $result = $this->authService->handleGoogleUser($googleUser);

        return $this->respondWithAuth($result, 'Logged in with Google successfully.');
    }

    /**
     * Helper to return standard user resource + token payload envelope.
     */
    private function respondWithAuth(AuthResult $authResult, string $message, int $code = 200): JsonResponse
    {
        return $this->success([
            'user' => new UserResource($authResult->user),
            'auth' => $authResult->tokens,
        ], $message, $code);
    }
}
