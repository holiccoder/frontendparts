<?php

namespace Tests\Feature\Affiliate;

use App\Models\Affiliate;
use App\Models\AffiliateReferral;
use App\Models\User;
use App\Services\Affiliates\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Referral tracking (SPEC §17.1 steps 2–3): `/r/{code}` records the click,
 * stamps the 30-day first-party cookie and 301-redirects to pricing; signup
 * links the referral to the new user; unknown or suspended codes silently
 * redirect without recording; clicks are rate-limited (§17.2 fraud controls).
 */
class ReferralTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_click_recorded_and_cookie_set()
    {
        $affiliate = Affiliate::factory()->create();

        $response = $this->get("/r/{$affiliate->code}");

        $response->assertStatus(301)
            ->assertRedirect(route('pricing'))
            ->assertCookie(ReferralService::COOKIE, $affiliate->code)
            ->assertCookieNotExpired(ReferralService::COOKIE);

        $referral = $affiliate->referrals()->sole();

        $this->assertSame('127.0.0.1', $referral->ip);
        $this->assertNotNull($referral->user_agent);
        $this->assertSame(route('pricing'), $referral->landing_url);
        $this->assertNotNull($referral->clicked_at);
        $this->assertNull($referral->referred_user_id);
        $this->assertNull($referral->converted_at);

        // Last-click: a second affiliate's link re-stamps the cookie.
        $other = Affiliate::factory()->create();

        $this->get("/r/{$other->code}")
            ->assertCookie(ReferralService::COOKIE, $other->code);
    }

    public function test_signup_links_referral_to_user()
    {
        $affiliate = Affiliate::factory()->create();

        // The click records the referral and sets the attribution cookie.
        $this->get("/r/{$affiliate->code}")
            ->assertCookie(ReferralService::COOKIE, $affiliate->code);

        // Signup carries the attribution cookie (the test client encrypts
        // request cookies transparently, like a real browser round-trip).
        $this->withCookie(ReferralService::COOKIE, $affiliate->code)
            ->post('/register', [
                'name' => 'Referred Buyer',
                'email' => 'buyer@example.com',
                'password' => 'password',
                'password_confirmation' => 'password',
            ])
            ->assertRedirect(route('dashboard', absolute: false));

        $buyer = User::query()->where('email', 'buyer@example.com')->sole();

        $referral = $affiliate->referrals()->sole();

        $this->assertSame($buyer->id, $referral->referred_user_id);
        $this->assertNotNull($referral->converted_at);

        // An organic signup (no cookie) leaves nothing behind.
        $this->post('/register', [
            'name' => 'Organic User',
            'email' => 'organic@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertSame(1, AffiliateReferral::query()
            ->whereNotNull('referred_user_id')
            ->count());
    }

    public function test_invalid_code_redirects_without_recording()
    {
        // Unknown code: silent 301, no click, no cookie.
        $response = $this->get('/r/not-a-code');

        $response->assertStatus(301)
            ->assertRedirect(route('pricing'))
            ->assertCookieMissing(ReferralService::COOKIE);

        $this->assertSame(0, AffiliateReferral::count());

        // A suspended affiliate's code is treated the same (§17.2 fraud control).
        $suspended = Affiliate::factory()->suspended()->create();

        $this->get("/r/{$suspended->code}")
            ->assertStatus(301)
            ->assertRedirect(route('pricing'))
            ->assertCookieMissing(ReferralService::COOKIE);

        $this->assertSame(0, AffiliateReferral::count());
    }

    public function test_click_rate_limited()
    {
        $affiliate = Affiliate::factory()->create();

        for ($i = 0; $i < 30; $i++) {
            $this->get("/r/{$affiliate->code}")->assertStatus(301);
        }

        $this->get("/r/{$affiliate->code}")->assertStatus(429);

        // The throttled click never recorded.
        $this->assertSame(30, $affiliate->referrals()->count());
    }
}
