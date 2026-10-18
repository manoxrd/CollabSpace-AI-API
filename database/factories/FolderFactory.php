<?php

namespace Database\Factories;

use App\Models\Folder;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Folder>
 */
class FolderFactory extends Factory
{
    protected $model = Folder::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'parent_id' => null,
            'name' => fake()->randomElement(['Design Assets', 'Documents', 'Prototypes', 'Research', 'Specifications']),
            'created_by' => User::factory(),
        ];
    }
}
