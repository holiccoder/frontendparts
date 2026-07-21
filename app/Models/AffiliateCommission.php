<?php

namespace App\Models;

use App\Enums\CommissionStatus;
use Database\Factories\AffiliateCommissionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * One commission earned on an attributed paid order (SPEC §17.2–17.3).
 *
 * Lifecycle: `pending` (order paid) → `payable` (refund window + holding
 * period elapsed, flipped by the daily `affiliates:mark-payable` command) →
 * `paid` (swept into a payout batch and marked paid by the admin). A
 * refund/chargeback before payout flips the commission to `voided`.
 *
 * Unique (order_id, affiliate_id): one commission per order per affiliate —
 * an order is attributed to at most one affiliate — which doubles as the
 * idempotency guard for replayed webhooks and repeat activations.
 */
class AffiliateCommission extends Model
{
    /** @use HasFactory<AffiliateCommissionFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'affiliate_id',
        'order_id',
        'referral_id',
        'amount',
        'currency',
        'status',
        'payable_at',
        'voided_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CommissionStatus::class,
            'amount' => 'decimal:2',
            'payable_at' => 'datetime',
        ];
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function referral(): BelongsTo
    {
        return $this->belongsTo(AffiliateReferral::class, 'referral_id');
    }

    /**
     * The payout batches this commission was swept into — at most one in
     * practice (enforced by the pivot's unique index, SPEC §17.3).
     */
    public function payout(): BelongsToMany
    {
        return $this->belongsToMany(AffiliatePayout::class, 'affiliate_commission_payout')
            ->withTimestamps();
    }
}
