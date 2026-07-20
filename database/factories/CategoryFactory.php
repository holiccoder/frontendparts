<?php

namespace Database\Factories;

use App\Enums\CategoryType;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'type' => fake()->randomElement(CategoryType::cases()),
            'zone' => null,
            'name' => $name,
            'slug' => Str::slug($name),
            'sort_order' => 0,
        ];
    }

    public function industry(): static
    {
        return $this->state(fn (): array => [
            'type' => CategoryType::Industry,
            'zone' => null,
        ]);
    }

    public function usage(?string $zone = null): static
    {
        return $this->state(fn (): array => [
            'type' => CategoryType::Usage,
            'zone' => $zone ?? fake()->randomElement([
                'Navigation', 'Opening', 'Content', 'Social proof', 'Conversion', 'Commerce', 'App UI',
            ]),
        ]);
    }
}
