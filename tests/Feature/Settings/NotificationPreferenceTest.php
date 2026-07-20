<?php

namespace Tests\Feature\Settings;

use App\Enums\NotificationCategory;
use App\Models\User;
use App\Services\Notifications\NotificationPreferences;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class NotificationPreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_preference_page_updates_flags()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/settings/notifications')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('settings/notifications')
                ->where('preferences.product_updates', true)
                ->where('preferences.blog', true)
                ->where('preferences.digest_frequency', 'weekly')
            );

        $this->actingAs($user)
            ->from('/settings/notifications')
            ->patch('/settings/notifications', [
                'product_updates' => false,
                'blog' => true,
                'digest_frequency' => 'monthly',
            ])
            ->assertSessionHasNoErrors();

        $preferences = app(NotificationPreferences::class)->for($user->fresh());

        $this->assertFalse($preferences['product_updates']);
        $this->assertTrue($preferences['blog']);
        $this->assertSame('monthly', $preferences['digest_frequency']);
    }

    public function test_signed_unsubscribe_link_opts_out()
    {
        $user = User::factory()->create();

        // The link carried by marketing mail works logged-out (the guest
        // is not actingAs anyone) and opts out of ALL marketing at once.
        $this->get(URL::signedRoute('unsubscribe', ['user' => $user->id]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('unsubscribed')
                ->where('email', $user->email)
            );

        $preferences = app(NotificationPreferences::class)->for($user->fresh());

        $this->assertFalse($preferences['product_updates']);
        $this->assertFalse($preferences['blog']);
        $this->assertSame('off', $preferences['digest_frequency']);
        $this->assertFalse($user->fresh()->wantsMarketing());
    }

    public function test_invalid_signature_rejected()
    {
        $user = User::factory()->create();

        // Unsigned link.
        $this->get(route('unsubscribe', ['user' => $user->id]))->assertForbidden();

        // Tampered signature.
        $this->get(route('unsubscribe', ['user' => $user->id]).'?signature=forged')->assertForbidden();

        // Preferences untouched: defaults still apply.
        $this->assertTrue($user->fresh()->wantsMarketing());
        $this->assertNull($user->fresh()->notification_preferences);
    }

    public function test_transactional_not_disabled()
    {
        $user = User::factory()->create();

        // An attempt to disable transactional mail alongside a full
        // marketing opt-out is ignored — the form request has no
        // transactional key, so validated() can never carry one.
        $this->actingAs($user)
            ->from('/settings/notifications')
            ->patch('/settings/notifications', [
                'product_updates' => false,
                'blog' => false,
                'digest_frequency' => 'off',
                'transactional' => false,
            ])
            ->assertSessionHasNoErrors();

        $user = $user->fresh();
        $preferences = app(NotificationPreferences::class);

        $this->assertFalse($user->wantsMarketing());
        $this->assertArrayNotHasKey('transactional', $user->notification_preferences ?? []);
        $this->assertTrue($preferences->allows($user, NotificationCategory::Transactional));
    }
}
