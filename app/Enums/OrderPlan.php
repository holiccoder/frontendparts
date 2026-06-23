<?php

namespace App\Enums;

enum OrderPlan: string
{
    case Free = 'free';
    case Starter = 'starter';
    case Pro = 'pro';
    case Enterprise = 'enterprise';

    public function monthlyPrice(): float
    {
        return match ($this) {
            self::Free => 0.00,
            self::Starter => 19.00,
            self::Pro => 49.00,
            self::Enterprise => 199.00,
        };
    }
}
