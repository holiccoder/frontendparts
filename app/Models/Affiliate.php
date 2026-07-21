<?php

namespace App\Models;

use App\Enums\AffiliateStatus;
use Database\Factories\AffiliateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * An affiliate program participant (SPEC §17): a registered user who joined
 * self-serve from the dashboard (accepting the Affiliate Terms, §17.7) and
 * promotes the product through the tracked link `/r/{code}`.
 *
 * Suspension is the admin fraud control (§17.2): a suspended affiliate keeps
 * its history but its code stops recording clicks and earning new
 * commissions.
 */
class Affiliate extends Model
{
    /** @use HasFactory<AffiliateFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'code',
        'status',
        'payout_method',
        'terms_accepted_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AffiliateStatus::class,
            'payout_method' => 'array',
            'terms_accepted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(AffiliateReferral::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(AffiliateCommission::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(AffiliatePayout::class);
    }

    public function isActive(): bool
    {
        return $this->status === AffiliateStatus::Active;
    }

    /**
     * The tracked referral link shared with the affiliate (SPEC §17.1).
     */
    public function referralUrl(): string
    {
        return route('affiliate.referral', ['code' => $this->code]);
    }

    /**
     * A fresh unique referral code: lowercase URL-safe, retried on the
     * (astronomically unlikely) collision so callers never race the unique
     * index.
     */
    public static function generateCode(): string
    {
        do {
            $code = Str::lower(Str::random(8));
        } while (self::query()->where('code', $code)->exists());

        return $code;
    }
}
