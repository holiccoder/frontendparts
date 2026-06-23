<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        User::all()->each(function (User $user): void {
            Order::factory()
                ->count(random_int(1, 3))
                ->for($user)
                ->create();
        });
    }
}
