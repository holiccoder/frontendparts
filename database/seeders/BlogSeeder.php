<?php

namespace Database\Seeders;

use App\Models\Blog;
use App\Models\User;
use Illuminate\Database\Seeder;

class BlogSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();

        if ($users->isEmpty()) {
            return;
        }

        Blog::factory()
            ->count(15)
            ->state(fn () => ['user_id' => $users->random()->id])
            ->create();
    }
}
