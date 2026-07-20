<?php

namespace App\Models;

use App\Enums\BillingPeriod;
use App\Enums\OrderPlan;
use App\Enums\PlanProvider;
use Database\Factories\PlanPriceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlanPrice extends Model
{
    /** @use HasFactory<PlanPriceFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'plan',
        'period',
        'provider',
        'amount',
        'currency',
        'paddle_price_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'plan' => OrderPlan::class,
            'period' => BillingPeriod::class,
            'provider' => PlanProvider::class,
            'amount' => 'decimal:2',
        ];
    }
}
