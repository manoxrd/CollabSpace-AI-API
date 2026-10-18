<?php

namespace App\Http\Controllers\Api;

use App\DTOs\AuthResult;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RefreshTokenRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected AuthService $authService
    ) {}

    /**
     * Register a new user and return JWT access/refresh token pair.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register($request->validated());

        return $this->respondWithAuth($result, 'Account registered successfully.', 201);
    }

    /**
     * Authenticate user credentials and return JWT access/refresh token pair.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login(
            $request->validated('email'),
            $request->validated('password')
        );

        return $this->respondWithAuth($result, 'Logged in successfully.');
    }

    /**
     * Rotate the refresh token and issue a fresh JWT access token.
     */
    public function refresh(RefreshTokenRequest $request): JsonResponse
    {
        $result = $this->authService->refresh($request->validated('refresh_token'));

        return $this->respondWithAuth($result, 'Token refreshed successfully.');
    }

    /**
     * Revoke the current refresh token and invalidate the JWT access token.
     */
    public function logout(RefreshTokenRequest $request): JsonResponse
    {
        $this->authService->logout($request->validated('refresh_token'));

        return $this->success([], 'Logged out successfully.');
    }

    /**
     * Send password reset link to user's email.
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $result = $this->authService->sendPasswordResetLink($request->validated('email'));

        return $this->success($result, $result['message']);
    }

    /**
     * Reset password using token.
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $result = $this->authService->resetPassword(
            $validated['email'],
            $validated['token'],
            $validated['password']
        );

        if (! $result['success']) {
            return $this->error([], $result['message'], $result['code'] ?? 400);
        }

        return $this->success([], $result['message']);
    }

    /**
     * Standardized envelope for user resource + token payload.
     */
    private function respondWithAuth(AuthResult $authResult, string $message, int $code = 200): JsonResponse
    {
        return $this->success([
            'user' => new UserResource($authResult->user),
            'auth' => $authResult->tokens,
        ], $message, $code);
    }
}
