<?php

namespace Database\Factories;

use App\Enums\ProjectExportStatus;
use App\Models\Project;
use App\Models\ProjectExport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectExport>
 */
class ProjectExportFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'user_id' => User::factory(),
            'framework' => 'react',
            'status' => ProjectExportStatus::Pending,
            'path' => null,
        ];
    }

    public function ready(): static
    {
        return $this->state(fn (): array => [
            'status' => ProjectExportStatus::Ready,
            'path' => 'project-exports/'.fake()->unique()->numberBetween(1, 1_000_000).'.zip',
        ]);
    }
}
