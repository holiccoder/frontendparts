<?php

namespace Database\Factories;

use App\Enums\ComponentLevel;
use App\Enums\SubmissionFramework;
use App\Enums\SubmissionStatus;
use App\Models\Category;
use App\Models\ComponentSubmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ComponentSubmission>
 */
class ComponentSubmissionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->unique()->words(3, true),
            'level' => fake()->randomElement(ComponentLevel::cases()),
            'usage_category_id' => Category::factory()->usage(),
            'framework' => SubmissionFramework::React,
            'description' => fake()->paragraph(),
            'react_code' => "export default function Demo() { return <div className=\"p-4\">Demo</div>; }\n",
            'vue_code' => null,
            'sample_data' => ['label' => 'Demo label'],
            'source_url' => null,
            'status' => SubmissionStatus::Pending,
            'review_note' => null,
            'component_id' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => ['status' => SubmissionStatus::Approved]);
    }

    public function rejected(): static
    {
        return $this->state(fn (): array => [
            'status' => SubmissionStatus::Rejected,
            'review_note' => fake()->sentence(),
        ]);
    }
}
