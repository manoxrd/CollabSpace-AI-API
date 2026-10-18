<?php

namespace Tests\Feature;

use App\Enums\UserAvailability;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_user_can_view_profile_overview_with_stats(): void
    {
        // Mohamed Salah is seeded with job_title 'UI/UX Designer', hours_worked 345, tasks, etc.
        $user = User::where('email', 'mohamed.salah@collabspace.ai')->first();
        $token = auth('api')->login($user);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/profile');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.name', 'Mohamed Salah')
            ->assertJsonPath('data.user.email', 'mohamed.salah@collabspace.ai')
            ->assertJsonPath('data.user.job_title', 'UI/UX Designer')
            ->assertJsonPath('data.user.stats.hours_worked', 345)
            ->assertJsonStructure([
                'data' => [
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'role',
                        'job_title',
                        'phone',
                        'stats' => ['completed_tasks_count', 'total_tasks_count', 'completion_rate', 'hours_worked'],
                        'recent_activities',
                    ],
                ],
            ]);
    }

    public function test_user_can_update_profile_and_settings(): void
    {
        $user = User::where('email', 'mohamed.salah@collabspace.ai')->first();
        $token = auth('api')->login($user);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/v1/profile', [
                'name' => 'Mohamed Salah Updated',
                'email' => 'mohamed.salah.new@collabspace.ai',
                'phone' => '+20 123 456 7890',
                'availability_status' => 'busy',
                'job_title' => 'Lead Product Designer',
                'years_of_experience' => 8,
                'current_team_project' => 'CollabSpace - Web Platform',
                'about' => 'Senior product designer with 8 years of experience leading UI/UX systems.',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.user.name', 'Mohamed Salah Updated')
            ->assertJsonPath('data.user.email', 'mohamed.salah.new@collabspace.ai')
            ->assertJsonPath('data.user.phone', '+20 123 456 7890')
            ->assertJsonPath('data.user.availability_status', 'busy')
            ->assertJsonPath('data.user.job_title', 'Lead Product Designer')
            ->assertJsonPath('data.user.years_of_experience', 8)
            ->assertJsonPath('data.user.current_team_project', 'CollabSpace - Web Platform');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Mohamed Salah Updated',
            'email' => 'mohamed.salah.new@collabspace.ai',
            'phone' => '+20 123 456 7890',
            'availability_status' => UserAvailability::Busy->value,
            'job_title' => 'Lead Product Designer',
            'years_of_experience' => 8,
            'current_team_project' => 'CollabSpace - Web Platform',
        ]);
    }

    public function test_user_can_upload_avatar_photo(): void
    {
        Storage::fake('public');
        $user = User::where('email', 'mohamed.salah@collabspace.ai')->first();
        $token = auth('api')->login($user);

        $file = UploadedFile::fake()->image('avatar.png', 200, 200);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->put('/api/v1/profile', [
                'avatar' => $file,
            ]);

        $response->assertStatus(200);
        $user->refresh();
        $this->assertNotNull($user->avatar_url);
        $this->assertStringContainsString('/avatars/', $user->avatar_url);
    }

    public function test_user_can_view_assigned_tasks_grouped_by_status(): void
    {
        $user = User::where('email', 'mohamed.salah@collabspace.ai')->first();
        $token = auth('api')->login($user);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/profile/tasks');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'todo',
                    'in_progress',
                    'done',
                ],
            ]);
    }

    public function test_user_can_view_uploaded_files(): void
    {
        $user = User::where('email', 'mohamed.salah@collabspace.ai')->first();
        $token = auth('api')->login($user);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/profile/files');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'files',
                ],
            ]);
    }

    public function test_unauthenticated_user_cannot_access_profile(): void
    {
        $response = $this->getJson('/api/v1/profile');

        $response->assertStatus(401);
    }
}
