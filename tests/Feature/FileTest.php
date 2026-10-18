<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\File;
use App\Models\Folder;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FileTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected User $otherUser;

    protected User $admin;

    protected Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'role' => UserRole::TeamMember,
        ]);

        $this->otherUser = User::factory()->create([
            'role' => UserRole::TeamMember,
        ]);

        $this->admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $this->project = Project::factory()->create();
    }

    public function test_user_can_view_file_details(): void
    {
        $file = File::factory()->create([
            'project_id' => $this->project->id,
            'uploaded_by' => $this->user->id,
            'name' => 'Design_Tokens.json',
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->getJson("/api/v1/files/{$file->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $file->id)
            ->assertJsonPath('data.name', 'Design_Tokens.json')
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'mime_type',
                    'size',
                    'size_formatted',
                    'downloads_count',
                    'url',
                    'project',
                    'uploader',
                    'created_at',
                    'uploaded_time_ago',
                ],
            ]);
    }

    public function test_user_can_download_file_and_downloads_count_increments(): void
    {
        Storage::fake('public');
        $storedPath = 'projects/1/files/sample_document.pdf';
        Storage::disk('public')->put($storedPath, 'Sample file content');

        $file = File::factory()->create([
            'project_id' => $this->project->id,
            'uploaded_by' => $this->user->id,
            'name' => 'sample_document.pdf',
            'path' => $storedPath,
            'disk' => 'public',
            'downloads_count' => 0,
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->get("/api/v1/files/{$file->id}/download");

        $response->assertOk();

        $file->refresh();
        $this->assertEquals(1, $file->downloads_count);
    }

    public function test_user_can_delete_own_file(): void
    {
        Storage::fake('public');
        $storedPath = 'projects/1/files/to_delete.pdf';
        Storage::disk('public')->put($storedPath, 'Content to delete');

        $file = File::factory()->create([
            'project_id' => $this->project->id,
            'uploaded_by' => $this->user->id,
            'path' => $storedPath,
            'disk' => 'public',
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->deleteJson("/api/v1/files/{$file->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('files', ['id' => $file->id]);
        Storage::disk('public')->assertMissing($storedPath);
    }

    public function test_unauthorized_user_cannot_delete_others_file(): void
    {
        $file = File::factory()->create([
            'project_id' => $this->project->id,
            'uploaded_by' => $this->otherUser->id,
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->deleteJson("/api/v1/files/{$file->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('files', ['id' => $file->id]);
    }

    public function test_user_can_create_folder_in_project(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->postJson("/api/v1/projects/{$this->project->id}/folders", [
                'name' => 'Design Assets',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Design Assets')
            ->assertJsonPath('data.project_id', $this->project->id)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'project_id',
                    'files_count',
                    'subfolders_count',
                    'creator',
                ],
            ]);

        $this->assertDatabaseHas('folders', [
            'name' => 'Design Assets',
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
        ]);
    }

    public function test_user_can_create_nested_subfolder(): void
    {
        $parentFolder = Folder::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->postJson("/api/v1/projects/{$this->project->id}/folders", [
                'name' => 'Wireframes',
                'parent_id' => $parentFolder->id,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Wireframes')
            ->assertJsonPath('data.parent_id', $parentFolder->id);

        $this->assertDatabaseHas('folders', [
            'name' => 'Wireframes',
            'parent_id' => $parentFolder->id,
        ]);
    }

    public function test_user_can_view_folder_contents(): void
    {
        $parentFolder = Folder::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
        ]);

        Folder::factory()->count(2)->create([
            'project_id' => $this->project->id,
            'parent_id' => $parentFolder->id,
            'created_by' => $this->user->id,
        ]);

        File::factory()->count(3)->create([
            'project_id' => $this->project->id,
            'folder_id' => $parentFolder->id,
            'uploaded_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->getJson("/api/v1/folders/{$parentFolder->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $parentFolder->id)
            ->assertJsonCount(2, 'data.children')
            ->assertJsonCount(3, 'data.files');
    }

    public function test_user_can_delete_own_folder(): void
    {
        Storage::fake('public');
        $storedPath = 'projects/1/files/sub_file.png';
        Storage::disk('public')->put($storedPath, 'image binary');

        $folder = Folder::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
        ]);

        $file = File::factory()->create([
            'project_id' => $this->project->id,
            'folder_id' => $folder->id,
            'path' => $storedPath,
            'disk' => 'public',
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->deleteJson("/api/v1/folders/{$folder->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('folders', ['id' => $folder->id]);
        $this->assertDatabaseMissing('files', ['id' => $file->id]);
        Storage::disk('public')->assertMissing($storedPath);
    }

    public function test_unauthorized_user_cannot_delete_others_folder(): void
    {
        $folder = Folder::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->otherUser->id,
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->deleteJson("/api/v1/folders/{$folder->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('folders', ['id' => $folder->id]);
    }
}
