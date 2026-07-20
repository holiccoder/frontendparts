<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Rendering zones (SPEC §10.1): public pages stay SSR-enabled; dashboard
 * and (future) checkout groups flip the SSR gateway off per-request via
 * the `ssr.skip` middleware, which exposes an X-SSR-Skipped header
 * outside production for observability.
 */
class SsrZoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_page_uses_ssr()
    {
        $this->assertTrue((bool) config('inertia.ssr.enabled'));

        $this->get('/')
            ->assertOk()
            ->assertHeaderMissing('X-SSR-Skipped');
    }

    public function test_dashboard_and_checkout_skip_ssr()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertHeader('X-SSR-Skipped', '1');

        // The public zone is untouched by the skip.
        $this->get('/')->assertHeaderMissing('X-SSR-Skipped');
    }

    public function test_auth_and_checkout_responses_carry_noindex()
    {
        $this->get('/login')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('/register')->assertHeader('X-Robots-Tag', 'noindex, nofollow');

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

        $this->actingAs($user)
            ->get('/settings/profile')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }
}
