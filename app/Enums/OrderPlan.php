<?php

namespace App\Enums;

use App\Models\PlanPrice;

enum OrderPlan: string
{
    case Free = 'free';
    case Starter = 'starter';
    case Pro = 'pro';

    public function price(BillingPeriod $period, PlanProvider $provider = PlanProvider::Paddle): ?PlanPrice
    {
        return PlanPrice::query()
            ->where('plan', $this)
            ->where('period', $period)
            ->where('provider', $provider)
            ->first();
    }
}
