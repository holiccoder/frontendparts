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
            'meta_title' => null,
            'meta_description' => null,
        ];
    }

    /**
     * Live post: published with a past publication timestamp.
     */
    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => 'published',
            'published_at' => fake()->dateTimeBetween('-6 months', '-1 day'),
        ]);
    }

    /**
     * Unpublished draft: never publicly visible.
     */
    public function draft(): static
    {
        return $this->state(fn (): array => [
            'status' => 'draft',
            'published_at' => null,
        ]);
    }

    /**
     * Scheduled post (SPEC §13.1): published flag set but the publication
     * timestamp is in the future, so it stays hidden until then.
     */
    public function scheduled(): static
    {
        return $this->state(fn (): array => [
            'status' => 'published',
            'published_at' => fake()->dateTimeBetween('+1 day', '+1 month'),
        ]);
    }
}
