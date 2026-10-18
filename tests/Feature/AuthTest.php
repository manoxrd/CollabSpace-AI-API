<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\RefreshToken;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_user_can_register_and_receive_jwt_and_refresh_token(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => ['id', 'name', 'email', 'role'],
                    'auth' => ['token_type', 'access_token', 'access_token_expires_at', 'raw_refresh_token', 'refresh_token_expires_at'],
                ],
            ])
            ->assertJsonPath('data.user.role', 'team_member');

        $user = User::where('email', 'john.doe@example.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals(UserRole::TeamMember, $user->role);
        $this->assertCount(1, $user->refreshTokens);
    }

    public function test_user_can_login_and_jwt_payload_contains_role_claim(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'mohamed.salah@collabspace.ai',
            'password' => 'password',
        ]);

        $response->assertStatus(200);

        $accessToken = $response->json('data.auth.access_token');
        $rawRefreshToken = $response->json('data.auth.raw_refresh_token');

        $this->assertNotEmpty($accessToken);
        $this->assertNotEmpty($rawRefreshToken);

        // Verify the raw token matches the SHA-256 hash in the database
        $storedToken = RefreshToken::findByRawToken($rawRefreshToken);
        $this->assertNotNull($storedToken);
        $this->assertEquals('mohamed.salah@collabspace.ai', $storedToken->user->email);

        // Verify custom claim "role" is injected into the JWT token payload
        $payload = auth('api')->setToken($accessToken)->getPayload();
        $this->assertEquals('team_member', $payload->get('role'));
        $this->assertEquals('mohamed.salah@collabspace.ai', $payload->get('email'));
    }

    public function test_user_can_access_profile_with_jwt(): void
    {
        $user = User::where('email', 'mohamed.salah@collabspace.ai')->first();
        $token = auth('api')->login($user);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/profile');

        $response->assertStatus(200)
            ->assertJsonPath('data.user.email', 'mohamed.salah@collabspace.ai')
            ->assertJsonPath('data.user.role', 'team_member');
    }

    public function test_refresh_token_rotation_rotates_and_issues_new_token_pair(): void
    {
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'mohamed.salah@collabspace.ai',
            'password' => 'password',
        ]);

        $oldRawToken = $loginResponse->json('data.auth.raw_refresh_token');

        // Refresh request
        $refreshResponse = $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $oldRawToken,
        ]);

        $refreshResponse->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'auth' => ['access_token', 'raw_refresh_token'],
                ],
            ]);

        $newRawToken = $refreshResponse->json('data.auth.raw_refresh_token');
        $this->assertNotEquals($oldRawToken, $newRawToken);

        // Old token should no longer be valid in the database
        $this->assertNull(RefreshToken::findByRawToken($oldRawToken));

        // New token must exist
        $this->assertNotNull(RefreshToken::findByRawToken($newRawToken));
    }

    public function test_user_can_logout_and_revoke_tokens(): void
    {
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'mohamed.salah@collabspace.ai',
            'password' => 'password',
        ]);

        $token = $loginResponse->json('data.auth.access_token');
        $rawRefreshToken = $loginResponse->json('data.auth.raw_refresh_token');

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/logout', [
                'refresh_token' => $rawRefreshToken,
            ]);

        $response->assertStatus(200);

        // Refresh token must be removed from DB
        $this->assertNull(RefreshToken::findByRawToken($rawRefreshToken));
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'mohamed.salah@collabspace.ai',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Invalid email or password.');
    }

    public function test_login_fails_when_user_is_suspended(): void
    {
        $user = User::where('email', 'mohamed.salah@collabspace.ai')->first();
        $user->update(['status' => 'suspended']);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'mohamed.salah@collabspace.ai',
            'password' => 'password',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Your account has been suspended. Please contact administration.');
    }

    public function test_refresh_fails_with_invalid_refresh_token(): void
    {
        $response = $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => 'invalid-nonexistent-token',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Invalid or expired refresh token.');
    }

    public function test_user_can_request_password_reset_and_reset_password(): void
    {
        Notification::fake();

        // 1. Request forgot password
        $forgotResponse = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'mohamed.salah@collabspace.ai',
        ]);

        $forgotResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        $user = User::where('email', 'mohamed.salah@collabspace.ai')->first();
        Notification::assertSentTo($user, ResetPasswordNotification::class);

        $resetToken = $forgotResponse->json('data.reset_token');
        $this->assertNotEmpty($resetToken);

        // 2. Reset password with received token
        $resetResponse = $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'mohamed.salah@collabspace.ai',
            'token' => $resetToken,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $resetResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        // 3. Login with new password
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'mohamed.salah@collabspace.ai',
            'password' => 'newpassword123',
        ]);

        $loginResponse->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_password_reset_fails_with_invalid_token(): void
    {
        $response = $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'mohamed.salah@collabspace.ai',
            'token' => 'invalid-token-12345',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Invalid or expired password reset token.');
    }

    public function test_google_auth_with_valid_token_creates_and_authenticates_user(): void
    {
        $abstractUser = \Mockery::mock(\Laravel\Socialite\Two\User::class);
        $abstractUser->shouldReceive('getId')->andReturn('google-unique-id-987654');
        $abstractUser->shouldReceive('getName')->andReturn('Google Test User');
        $abstractUser->shouldReceive('getEmail')->andReturn('google.user@example.com');
        $abstractUser->shouldReceive('getAvatar')->andReturn('https://lh3.googleusercontent.com/avatar.jpg');

        $provider = \Mockery::mock(GoogleProvider::class);
        $provider->shouldReceive('userFromToken')->with('valid-google-client-token')->andReturn($abstractUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->postJson('/api/v1/auth/google/callback', [
            'token' => 'valid-google-client-token',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'google.user@example.com')
            ->assertJsonPath('data.user.name', 'Google Test User')
            ->assertJsonStructure([
                'data' => [
                    'user' => ['id', 'email', 'name', 'role'],
                    'auth' => ['access_token', 'raw_refresh_token'],
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'google.user@example.com',
            'google_id' => 'google-unique-id-987654',
        ]);
    }

    public function test_google_auth_with_invalid_token_returns_401(): void
    {
        $provider = \Mockery::mock(GoogleProvider::class);
        $provider->shouldReceive('userFromToken')->with('invalid-token')->andThrow(new \Exception('Bad token'));

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->postJson('/api/v1/auth/google/callback', [
            'token' => 'invalid-token',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Invalid Google token.');
    }

    public function test_google_auth_fails_when_token_is_missing(): void
    {
        $response = $this->postJson('/api/v1/auth/google/callback', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['token']);
    }
}
