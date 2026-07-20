<?php

namespace Database\Factories;

use App\Enums\BillingPeriod;
use App\Enums\OrderPlan;
use App\Enums\PlanProvider;
use App\Models\PlanPrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlanPrice>
 */
class PlanPriceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plan' => fake()->randomElement(OrderPlan::cases()),
            'period' => fake()->randomElement(BillingPeriod::cases()),
            'provider' => PlanProvider::Paddle,
            'amount' => fake()->randomFloat(2, 1, 500),
            'currency' => 'USD',
            'paddle_price_id' => null,
        ];
    }

    public function paddle(): static
    {
        return $this->state(fn (): array => [
            'provider' => PlanProvider::Paddle,
            'currency' => 'USD',
        ]);
    }

    public function domestic(): static
    {
        return $this->state(fn (): array => [
            'provider' => PlanProvider::Domestic,
            'currency' => 'CNY',
            'paddle_price_id' => null,
        ]);
    }
}
