<?php

namespace App\Models;

use App\Enums\PayoutStatus;
use Database\Factories\AffiliatePayoutFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A monthly payout batch to one affiliate (SPEC §17.3): groups their
 * `payable` commissions once the total clears the payout threshold; the
 * admin pays it manually (PayPal / Wise / CN rails) and marks it `paid` with
 * the provider reference. `method` snapshots the payout coordinates at batch
 * time so later edits to the affiliate's payout method never rewrite
 * history.
 */
class AffiliatePayout extends Model
{
    /** @use HasFactory<AffiliatePayoutFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'affiliate_id',
        'amount',
        'currency',
        'status',
        'method',
        'reference',
        'paid_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PayoutStatus::class,
            'amount' => 'decimal:2',
            'method' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function commissions(): BelongsToMany
    {
        return $this->belongsToMany(AffiliateCommission::class, 'affiliate_commission_payout')
            ->withTimestamps();
    }
}
