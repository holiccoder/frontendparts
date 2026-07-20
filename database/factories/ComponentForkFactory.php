<?php

namespace Database\Factories;

use App\Enums\ComponentForkStatus;
use App\Models\Component;
use App\Models\ComponentFork;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ComponentFork>
 */
class ComponentForkFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'component_id' => Component::factory(),
            'framework' => 'react',
            'entry_file' => null,
            'files' => [],
            'status' => ComponentForkStatus::Pending,
            'error' => null,
            'preview_paths' => null,
            'preview_built_at' => null,
        ];
    }
}
