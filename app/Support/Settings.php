<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

class Settings
{
    /**
     * Registered keys with their typed defaults (SPEC §8.7).
     * `null` means "no value / unlimited" for that key.
     *
     * @var array<string, mixed>
     */
    private const DEFAULTS = [
        'plans.project_limit.free' => 1,
        'plans.project_limit.starter' => 3,
        'plans.project_limit.pro' => null,
        'plans.project_limit.team' => null,
        'billing.refund_window_days' => 14,
        'features.preview_dark_toggle' => true,
        'features.tree_interactions' => true,
        'features.live_edit' => false,
        'features.ai_search' => false,
        'features.ai_variants' => false,
        'goals.launch_component_target' => 100,
        'goals.components_per_month' => 20,
        'goals.organic_visits_monthly' => 10000,
        'goals.signup_conversion_pct' => 5,
        'goals.paid_conversion_pct_min' => 3,
        'goals.paid_conversion_pct_max' => 5,
        'goals.churn_max_pct' => 5,
        'goals.mrr_target_usd' => 2000,
        'fx.cny_to_usd' => 0.14,
        'affiliate.commission_rate' => 30,
        'affiliate.cookie_days' => 30,
        'affiliate.recurring_months' => 12,
        'affiliate.holding_days' => 30,
        'affiliate.payout_threshold' => 50,
    ];

    /**
     * Per-instance resolved values.
     *
     * @var array<string, mixed>
     */
    private array $resolved = [];

    /**
     * All registered keys with their defaults.
     *
     * @return array<string, mixed>
     */
    public function registered(): array
    {
        return self::DEFAULTS;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, self::DEFAULTS);
    }

    public function get(string $key): mixed
    {
        $this->ensureRegistered($key);

        if (array_key_exists($key, $this->resolved)) {
            return $this->resolved[$key];
        }

        return $this->resolved[$key] = Cache::rememberForever($this->cacheKey($key), function () use ($key): mixed {
            $stored = Setting::query()->where('key', $key)->value('value');

            if ($stored === null) {
                return self::DEFAULTS[$key];
            }

            return json_decode($stored, true, flags: JSON_THROW_ON_ERROR);
        });
    }

    public function set(string $key, mixed $value): void
    {
        $this->ensureRegistered($key);

        Setting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => json_encode($value, JSON_THROW_ON_ERROR)],
        );

        $this->flush($key);
    }

    public function forget(string $key): void
    {
        $this->ensureRegistered($key);

        Setting::query()->where('key', $key)->delete();

        $this->flush($key);
    }

    private function flush(string $key): void
    {
        unset($this->resolved[$key]);

        Cache::forget($this->cacheKey($key));
    }

    private function ensureRegistered(string $key): void
    {
        if (! $this->has($key)) {
            throw new InvalidArgumentException("Unknown settings key [{$key}].");
        }
    }

    private function cacheKey(string $key): string
    {
        return "settings.{$key}";
    }
}
