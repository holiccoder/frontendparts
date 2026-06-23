<?php

namespace Database\Factories;

use App\Models\Blog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Blog>
 */
class BlogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->sentence(6);
        $status = fake()->randomElement(['draft', 'published', 'archived']);

        return [
            'user_id' => User::factory(),
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1, 1_000_000),
            'excerpt' => fake()->paragraph(),
            'body' => collect(fake()->paragraphs(5))->implode("\n\n"),
            'featured_image' => null,
            'status' => $status,
            'published_at' => $status === 'published' ? fake()->dateTimeBetween('-6 months', 'now') : null,
        ];
    }
}
