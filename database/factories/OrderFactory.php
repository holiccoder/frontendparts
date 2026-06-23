<?php

namespace Database\Factories;

use App\Enums\BillingPeriod;
use App\Enums\OrderPlan;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $plan = fake()->randomElement(OrderPlan::cases());
        $period = fake()->randomElement(BillingPeriod::cases());
        $monthly = $plan->monthlyPrice();
        $amount = $period === BillingPeriod::Yearly ? $monthly * 12 * 0.8 : $monthly;
        $startsAt = fake()->dateTimeBetween('-1 year', 'now');
        $endsAt = (clone $startsAt)->modify($period === BillingPeriod::Yearly ? '+1 year' : '+1 month');

        return [
            'user_id' => User::factory(),
            'plan' => $plan,
            'status' => fake()->randomElement(OrderStatus::cases()),
            'billing_period' => $period,
            'amount' => $amount,
            'currency' => 'USD',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'cancelled_at' => null,
        ];
    }
}
