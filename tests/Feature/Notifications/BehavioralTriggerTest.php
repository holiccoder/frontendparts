<?php

namespace Tests\Feature\Notifications;

use App\Enums\BillingPeriod;
use App\Enums\ComponentEventType;
use App\Enums\OrderPlan;
use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\Component;
use App\Models\ComponentEvent;
use App\Models\Order;
use App\Models\User;
use App\Notifications\CategoryInterestNotification;
use App\Notifications\DormantDownloaderNotification;
use App\Services\Notifications\NotificationPreferences;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * SPEC §16.4 P3 behavioral personalization — the two B2-style triggers
 * beyond the blur-gate: B9 category-interest (≥5 views in one usage
 * category within 14 days, no download yet) and B10 dormant-downloader
 * (≥2 downloads, no events for ≥14 days). Both are ProductUpdates marketing
 * mail, throttled to at most one send per 14 days per user.
 */
class BehavioralTriggerTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_interest_fires_at_threshold_and_not_below()
    {
        Notification::fake();

        $user = User::factory()->create();
        $category = Category::factory()->usage()->create(['name' => 'Hero sections']);
        $components = Component::factory()->published()->count(2)->create(['usage_category_id' => $category->id]);

        // A popular component in a different category must not leak into
        // the personalized picks (anonymous event: never qualifies anyone).
        $other = Component::factory()->published()->create();
        $other->recordEvent(ComponentEventType::View);

        // Four views in the same usage category within the window — below
        // the threshold of 5.
        foreach (range(1, 4) as $i) {
            $components[0]->recordEvent(ComponentEventType::View, $user);
        }

        $this->artisan('mail:run-sequences')->assertSuccessful();

        Notification::assertNotSentTo($user, CategoryInterestNotification::class);

        // The fifth view in the same category crosses the threshold —
        // counting is per category, not per component.
        $components[1]->recordEvent(ComponentEventType::View, $user);

        $this->artisan('mail:run-sequences')->assertSuccessful();

        Notification::assertSentTo(
            $user,
            CategoryInterestNotification::class,
            function (CategoryInterestNotification $notification) use ($user, $category, $components, $other): bool {
                $html = (string) $notification->toMail($user)->render();

                return $notification->category->is($category)
                    && str_contains($html, 'Hero sections')
                    && str_contains($html, $components[0]->name)
                    && ! str_contains($html, $other->name);
            },
        );
    }

    public function test_category_interest_excludes_paid_users_and_downloaders()
    {
        Notification::fake();

        $category = Category::factory()->usage()->create();
        $component = Component::factory()->published()->create(['usage_category_id' => $category->id]);

        // Paid users never get B9 (free-audience gate at send time).
        $paidUser = User::factory()->create();
        $this->grantPaidEntitlement($paidUser);

        // A user who already downloaded (ever) converted past browsing —
        // B9 targets pure browsers; lapsed downloaders belong to B10.
        $downloader = User::factory()->create();

        foreach ([$paidUser, $downloader] as $candidate) {
            foreach (range(1, 5) as $i) {
                $component->recordEvent(ComponentEventType::View, $candidate);
            }
        }

        $component->recordEvent(ComponentEventType::Download, $downloader);

        $this->artisan('mail:run-sequences')->assertSuccessful();

        Notification::assertNotSentTo($paidUser, CategoryInterestNotification::class);
        Notification::assertNotSentTo($downloader, CategoryInterestNotification::class);
    }

    public function test_category_interest_throttles_to_one_send_per_14_days()
    {
        Notification::fake();

        $user = User::factory()->create();
        $category = Category::factory()->usage()->create();
        $component = Component::factory()->published()->create(['usage_category_id' => $category->id]);

        foreach (range(1, 5) as $i) {
            $component->recordEvent(ComponentEventType::View, $user);
        }

        $this->artisan('mail:run-sequences')->assertSuccessful();

        Notification::assertSentTimes(CategoryInterestNotification::class, 1);

        // Re-runs within the 14-day throttle never resend.
        $this->artisan('mail:run-sequences')->assertSuccessful();
        $this->travel(1)->days();
        $this->artisan('mail:run-sequences')->assertSuccessful();

        Notification::assertSentTimes(CategoryInterestNotification::class, 1);

        // After the throttle window, fresh interest sends exactly one more
        // mail (the original views aged out of the 14-day signal window, so
        // new views are required to re-qualify).
        $this->travel(15)->days();

        foreach (range(1, 5) as $i) {
            $component->recordEvent(ComponentEventType::View, $user);
        }

        $this->artisan('mail:run-sequences')->assertSuccessful();

        Notification::assertSentTimes(CategoryInterestNotification::class, 2);
    }

    public function test_dormant_downloader_fires_after_inactivity_window()
    {
        Notification::fake();

        $user = User::factory()->create();
        $downloaded = Component::factory()->published()->create(['created_at' => now()->subDays(30)]);

        // Value extracted 20/19 days ago, then silence — last activity is
        // beyond the 14-day dormancy window.
        $this->downloadFor($user, $downloaded, 20);
        $this->copyFor($user, $downloaded, 19);

        // Only drops after the last activity are "new since you left".
        $newDrop = Component::factory()->published()->create(['created_at' => now()->subDays(5)]);
        $oldDrop = Component::factory()->published()->create(['created_at' => now()->subDays(25)]);

        // Same value history but still active (a view just now) → excluded.
        $activeUser = User::factory()->create();
        $this->downloadFor($activeUser, $downloaded, 20);
        $this->downloadFor($activeUser, $downloaded, 19);
        $downloaded->recordEvent(ComponentEventType::View, $activeUser);

        $this->artisan('mail:run-sequences')->assertSuccessful();

        Notification::assertSentTo(
            $user,
            DormantDownloaderNotification::class,
            function (DormantDownloaderNotification $notification) use ($user, $newDrop, $oldDrop, $downloaded): bool {
                $html = (string) $notification->toMail($user)->render();

                return str_contains($html, $newDrop->name)
                    && ! str_contains($html, $oldDrop->name)
                    && ! str_contains($html, $downloaded->name);
            },
        );
        Notification::assertNotSentTo($activeUser, DormantDownloaderNotification::class);
    }

    public function test_dormant_downloader_requires_two_downloads_and_new_content()
    {
        Notification::fake();

        $old = Component::factory()->published()->create(['created_at' => now()->subDays(30)]);

        // One download only — below the value-extraction threshold of 2.
        $singleDownload = User::factory()->create();
        $this->downloadFor($singleDownload, $old, 20);

        // Two downloads and quiet, but nothing was published since the last
        // activity — the send is suppressed (no progress recorded).
        $noNewContent = User::factory()->create();
        $this->downloadFor($noNewContent, $old, 20);
        $this->downloadFor($noNewContent, $old, 19);

        $this->artisan('mail:run-sequences')->assertSuccessful();

        Notification::assertNotSentTo($singleDownload, DormantDownloaderNotification::class);
        Notification::assertNotSentTo($noNewContent, DormantDownloaderNotification::class);
    }

    public function test_dormant_downloader_throttles_to_one_send_per_14_days()
    {
        Notification::fake();

        $user = User::factory()->create();
        $downloaded = Component::factory()->published()->create(['created_at' => now()->subDays(30)]);
        $this->downloadFor($user, $downloaded, 20);
        $this->downloadFor($user, $downloaded, 19);

        Component::factory()->published()->create(['created_at' => now()->subDays(5)]);

        $this->artisan('mail:run-sequences')->assertSuccessful();

        Notification::assertSentTimes(DormantDownloaderNotification::class, 1);

        // Re-runs within the 14-day throttle never resend.
        $this->artisan('mail:run-sequences')->assertSuccessful();
        $this->travel(1)->days();
        $this->artisan('mail:run-sequences')->assertSuccessful();

        Notification::assertSentTimes(DormantDownloaderNotification::class, 1);

        // 16 days after the first send the throttle has expired; the user
        // is still dormant and the drop is still newer than their last
        // activity, so exactly one resend goes out.
        $this->travel(15)->days();
        $this->artisan('mail:run-sequences')->assertSuccessful();

        Notification::assertSentTimes(DormantDownloaderNotification::class, 2);
    }

    public function test_dormant_downloader_excludes_paid_users()
    {
        Notification::fake();

        $paidUser = User::factory()->create();
        $this->grantPaidEntitlement($paidUser);

        $downloaded = Component::factory()->published()->create(['created_at' => now()->subDays(30)]);
        $this->downloadFor($paidUser, $downloaded, 20);
        $this->downloadFor($paidUser, $downloaded, 19);

        Component::factory()->published()->create(['created_at' => now()->subDays(5)]);

        $this->artisan('mail:run-sequences')->assertSuccessful();

        Notification::assertNotSentTo($paidUser, DormantDownloaderNotification::class);
    }

    public function test_behavioral_triggers_respect_product_updates_opt_out()
    {
        Notification::fake();

        $preferences = app(NotificationPreferences::class);

        // A B9 candidate: 5 views in one usage category, never downloaded.
        $browser = User::factory()->create();
        $category = Category::factory()->usage()->create();
        $component = Component::factory()->published()->create(['usage_category_id' => $category->id]);

        foreach (range(1, 5) as $i) {
            $component->recordEvent(ComponentEventType::View, $browser);
        }

        // A B10 candidate: 2 downloads, 19+ days quiet, and the component
        // above is a drop newer than the last activity.
        $downloader = User::factory()->create();
        $this->downloadFor($downloader, $component, 20);
        $this->downloadFor($downloader, $component, 19);

        $preferences->update($browser, ['product_updates' => false]);
        $preferences->update($downloader, ['product_updates' => false]);

        $this->artisan('mail:run-sequences')->assertSuccessful();

        Notification::assertNotSentTo($browser, CategoryInterestNotification::class);
        Notification::assertNotSentTo($downloader, DormantDownloaderNotification::class);
    }

    private function grantPaidEntitlement(User $user): void
    {
        Order::factory()->create([
            'user_id' => $user->id,
            'plan' => OrderPlan::Pro,
            'status' => OrderStatus::Active,
            'billing_period' => BillingPeriod::Lifetime,
            'starts_at' => now()->subDays(30),
            'ends_at' => null,
        ]);
    }

    private function downloadFor(User $user, Component $component, int $daysAgo): void
    {
        $this->eventFor($user, $component, ComponentEventType::Download, $daysAgo);
    }

    private function copyFor(User $user, Component $component, int $daysAgo): void
    {
        $this->eventFor($user, $component, ComponentEventType::Copy, $daysAgo);
    }

    private function eventFor(User $user, Component $component, ComponentEventType $type, int $daysAgo): void
    {
        ComponentEvent::factory()->create([
            'component_id' => $component->id,
            'user_id' => $user->id,
            'type' => $type,
            'created_at' => now()->subDays($daysAgo),
        ]);
    }
}
