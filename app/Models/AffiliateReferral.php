<?php

namespace App\Models;

use Database\Factories\AffiliateReferralFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One tracked click on an affiliate's `/r/{code}` link (SPEC §17.1 step 2).
 * The click is anonymous until the visitor signs up — the Registered
 * listener then links the record to the new user (`referred_user_id` +
 * `converted_at`), which is the signup attribution checkout falls back to
 * when no referral code rides the order.
 */
class AffiliateReferral extends Model
{
    /** @use HasFactory<AffiliateReferralFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'affiliate_id',
        'referred_user_id',
        'clicked_at',
        'ip',
        'user_agent',
        'landing_url',
        'converted_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'clicked_at' => 'datetime',
            'converted_at' => 'datetime',
        ];
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function referredUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_user_id');
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(AffiliateCommission::class, 'referral_id');
    }
}
