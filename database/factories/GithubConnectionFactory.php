<?php

namespace Database\Factories;

use App\Models\GithubConnection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GithubConnection>
 */
class GithubConnectionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'github_id' => (string) fake()->unique()->randomNumber(8),
            'github_login' => fake()->unique()->userName(),
            'token' => 'gho_'.fake()->sha1(),
        ];
    }
}
