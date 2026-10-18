<?php

namespace App\Services;

use App\DTOs\AuthResult;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Support\Carbon;

class AuthTokenService
{
    /**
     * Issue an access token and refresh token pair for the given user.
     */
    public function issueTokenPair(User $user): AuthResult
    {
        $refreshToken = RefreshToken::generateToken();

        $user->refreshTokens()->create([
            'token' => $refreshToken['hash'],
            'expires_at' => $this->getRefreshTokenExpiry(),
        ]);

        $accessToken = auth('api')->login($user);

        return new AuthResult(
            user: $user,
            tokens: $this->formatTokenPayload($accessToken, $refreshToken['raw'])
        );
    }

    /**
     * Rotate a raw refresh token and return the user with the new token pair.
     * Returns null if the token does not exist or has expired.
     */
    public function rotateToken(string $rawRefreshToken): ?AuthResult
    {
        $refreshToken = RefreshToken::findByRawToken($rawRefreshToken);

        if (! $refreshToken || $refreshToken->isExpired()) {
            return null;
        }

        $user = $refreshToken->user;
        $newRefreshToken = RefreshToken::generateToken();

        $refreshToken->update([
            'token' => $newRefreshToken['hash'],
            'expires_at' => $this->getRefreshTokenExpiry(),
        ]);

        $accessToken = auth('api')->login($user);

        return new AuthResult(
            user: $user,
            tokens: $this->formatTokenPayload($accessToken, $newRefreshToken['raw'])
        );
    }

    /**
     * Revoke the given raw refresh token and logout the current JWT session.
     */
    public function revoke(string $rawRefreshToken): void
    {
        $refreshToken = RefreshToken::findByRawToken($rawRefreshToken);

        if ($refreshToken) {
            $refreshToken->delete();
        }

        auth('api')->logout();
    }

    /**
     * Format the standard token envelope, eliminating dictionary duplication.
     *
     * @return array{
     *     token_type: string,
     *     access_token: string,
     *     access_token_expires_at: string,
     *     raw_refresh_token: string,
     *     refresh_token_expires_at: string
     * }
     */
    private function formatTokenPayload(string $accessToken, string $rawRefreshToken): array
    {
        return [
            'token_type' => 'Bearer',
            'access_token' => $accessToken,
            'access_token_expires_at' => now()->addMinutes((int) config('jwt.ttl', 60))->toISOString(),
            'raw_refresh_token' => $rawRefreshToken,
            'refresh_token_expires_at' => $this->getRefreshTokenExpiry()->toISOString(),
        ];
    }

    /**
     * Get the refresh token expiry timestamp.
     */
    private function getRefreshTokenExpiry(): Carbon
    {
        return now()->addDays((int) config('jwt.refresh_token_ttl_days', 14));
    }
}
