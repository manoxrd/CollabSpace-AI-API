<?php

namespace Database\Factories;

use App\Models\File;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<File>
 */
class FileFactory extends Factory
{
    public function definition(): array
    {
        $filename = fake()->randomElement([
            'UX_Research_Summary.pdf',
            'Dashboard_Layout_React.jsx',
            'Database_Schema_v2.sql',
            'Design_Tokens.json',
            'Architecture_Diagram.png',
            'Sprint_Backlog.xlsx',
        ]);

        return [
            'project_id' => Project::factory(),
            'folder_id' => null,
            'task_id' => null,
            'name' => $filename,
            'path' => 'files/'.fake()->uuid().'_'.$filename,
            'disk' => 'public',
            'mime_type' => fake()->randomElement(['application/pdf', 'application/json', 'image/png', 'text/plain']),
            'size' => fake()->numberBetween(10240, 15728640), // 10KB to 15MB
            'downloads_count' => fake()->numberBetween(0, 45),
            'uploaded_by' => User::factory(),
        ];
    }
}
