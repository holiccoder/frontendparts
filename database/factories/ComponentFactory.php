<?php

namespace Database\Factories;

use App\Enums\AccessLevel;
use App\Enums\ComponentLevel;
use App\Enums\ComponentStatus;
use App\Models\Category;
use App\Models\Component;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Component>
 */
class ComponentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 1_000_000),
            'name' => $name,
            'level' => fake()->randomElement(ComponentLevel::cases()),
            'usage_category_id' => Category::factory()->usage(),
            'access_level' => fake()->randomElement(AccessLevel::cases()),
            'status' => ComponentStatus::Draft,
            'version' => '1.0.0',
            'source_name' => null,
            'source_url' => null,
            'deps' => null,
        ];
    }

    public function element(): static
    {
        return $this->state(fn (): array => ['level' => ComponentLevel::Element]);
    }

    public function block(): static
    {
        return $this->state(fn (): array => ['level' => ComponentLevel::Block]);
    }

    public function section(): static
    {
        return $this->state(fn (): array => ['level' => ComponentLevel::Section]);
    }

    public function page(): static
    {
        return $this->state(fn (): array => ['level' => ComponentLevel::Page]);
    }

    public function free(): static
    {
        return $this->state(fn (): array => ['access_level' => AccessLevel::Free]);
    }

    public function paid(): static
    {
        return $this->state(fn (): array => ['access_level' => AccessLevel::Paid]);
    }

    public function draft(): static
    {
        return $this->state(fn (): array => ['status' => ComponentStatus::Draft]);
    }

    public function inReview(): static
    {
        return $this->state(fn (): array => ['status' => ComponentStatus::InReview]);
    }

    public function published(): static
    {
        return $this->state(fn (): array => ['status' => ComponentStatus::Published]);
    }
}
