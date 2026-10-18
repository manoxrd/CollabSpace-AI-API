<?php

namespace App\DTOs;

use App\Models\User;

readonly class AuthResult
{
    /**
     * @param array{
     *     token_type: string,
     *     access_token: string,
     *     access_token_expires_at: string,
     *     raw_refresh_token: string,
     *     refresh_token_expires_at: string
     * } $tokens
     */
    public function __construct(
        public User $user,
        public array $tokens
    ) {}
}
