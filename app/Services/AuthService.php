<?php

namespace App\Services;

use App\DTOs\AuthResult;
use App\Enums\UserRole;
use App\Exceptions\AccountSuspendedException;
use App\Exceptions\InvalidCredentialsException;
use App\Exceptions\InvalidRefreshTokenException;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class AuthService
{
    public function __construct(
        protected AuthTokenService $authTokenService
    ) {}

    /**
     * Register a new user and issue an access/refresh token pair.
     */
    public function register(array $data): AuthResult
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'], // Auto-hashed by User model cast
                'role' => UserRole::TeamMember->value,
                'status' => 'active',
                'joined_at' => now(),
            ]);

            Log::info('Auth: New user registered', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            return $this->authTokenService->issueTokenPair($user);
        });
    }

    /**
     * Authenticate user credentials and issue an access/refresh token pair.
     *
     * @throws InvalidCredentialsException
     * @throws AccountSuspendedException
     */
    public function login(string $email, string $password): AuthResult
    {
        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            Log::warning('Auth: Failed login attempt', [
                'email' => $email,
            ]);

            throw new InvalidCredentialsException;
        }

        if ($user->status === 'suspended') {
            Log::warning('Auth: Suspended user login attempt', [
                'user_id' => $user->id,
                'email' => $email,
            ]);

            throw new AccountSuspendedException;
        }

        Log::info('Auth: User logged in successfully', [
            'user_id' => $user->id,
            'email' => $user->email,
        ]);

        return $this->authTokenService->issueTokenPair($user);
    }

    /**
     * Refresh access token via refresh token rotation.
     *
     * @throws InvalidRefreshTokenException
     */
    public function refresh(string $rawRefreshToken): AuthResult
    {
        $result = $this->authTokenService->rotateToken($rawRefreshToken);

        if (! $result) {
            throw new InvalidRefreshTokenException;
        }

        return $result;
    }

    /**
     * Invalidate refresh token and logout session.
     */
    public function logout(string $rawRefreshToken): void
    {
        $this->authTokenService->revoke($rawRefreshToken);
    }

    /**
     * Authenticate or register a user via Google OAuth and issue JWT pair.
     */
    public function handleGoogleUser(SocialiteUser $googleUser): AuthResult
    {
        return DB::transaction(function () use ($googleUser) {
            $user = User::where('google_id', $googleUser->getId())
                ->orWhere('email', $googleUser->getEmail())
                ->first();

            if ($user) {
                $user->update([
                    'google_id' => $googleUser->getId(),
                    'avatar_url' => $user->avatar_url ?? $googleUser->getAvatar(),
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ]);
            } else {
                $user = User::create([
                    'name' => $googleUser->getName() ?? 'Google User',
                    'email' => $googleUser->getEmail(),
                    'password' => Str::random(32), // Auto-hashed
                    'google_id' => $googleUser->getId(),
                    'avatar_url' => $googleUser->getAvatar(),
                    'role' => UserRole::TeamMember->value,
                    'status' => 'active',
                    'joined_at' => now(),
                    'email_verified_at' => now(),
                ]);
            }

            return $this->authTokenService->issueTokenPair($user);
        });
    }

    /**
     * Generate password reset token, save it, and send reset email.
     *
     * @return array{success: bool, message: string, reset_token?: ?string}
     */
    public function sendPasswordResetLink(string $email): array
    {
        $user = User::where('email', $email)->first();

        if (! $user) {
            // OWASP recommendation: do not leak whether user email exists
            return [
                'success' => true,
                'message' => 'If this email is registered, a password reset link has been sent.',
            ];
        }

        $rawToken = Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            [
                'token' => Hash::make($rawToken),
                'created_at' => now(),
            ]
        );

        $user->notify(new ResetPasswordNotification($rawToken));

        return [
            'success' => true,
            'message' => 'Password reset link sent to your email.',
            'reset_token' => app()->environment(['local', 'testing']) ? $rawToken : null,
        ];
    }

    /**
     * Reset the user password using a verified reset token.
     *
     * @return array{success: bool, message: string, code?: int}
     */
    public function resetPassword(string $email, string $token, string $password): array
    {
        $record = DB::table('password_reset_tokens')->where('email', $email)->first();

        if (! $record || ! Hash::check($token, $record->token)) {
            return [
                'success' => false,
                'message' => 'Invalid or expired password reset token.',
                'code' => 400,
            ];
        }

        // Token expires after 60 minutes
        if (Carbon::parse($record->created_at)->addMinutes(60)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();

            return [
                'success' => false,
                'message' => 'Password reset token has expired. Please request a new one.',
                'code' => 400,
            ];
        }

        $user = User::where('email', $email)->first();

        if (! $user) {
            return [
                'success' => false,
                'message' => 'User not found.',
                'code' => 404,
            ];
        }

        // Update password and revoke all existing refresh tokens for security
        $user->update(['password' => $password]);
        $user->refreshTokens()->delete();

        // Delete used reset token
        DB::table('password_reset_tokens')->where('email', $email)->delete();

        return [
            'success' => true,
            'message' => 'Password has been reset successfully.',
        ];
    }
}
