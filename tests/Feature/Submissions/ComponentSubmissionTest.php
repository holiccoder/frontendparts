<?php

namespace Tests\Feature\Submissions;

use App\Enums\ComponentLevel;
use App\Enums\SubmissionStatus;
use App\Models\Category;
use App\Models\ComponentSubmission;
use App\Models\User;
use App\Notifications\SubmissionCreatedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * User-side community submissions (task 5.3, PRD §4.2 P3): validated create
 * with per-framework code requirements and JSON sample data, own-rows-only
 * visibility, NFR-10 creation rate limit and the admin inbox alert.
 */
class ComponentSubmissionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function validPayload(Category $usage): array
    {
        return [
            'name' => 'Pricing Badge',
            'level' => 'block',
            'usage_category_id' => $usage->id,
            'framework' => 'react',
            'description' => 'A pricing card badge recreated from a live SaaS pricing page.',
            'react_code' => "export default function PricingBadge({ label = 'Hot' }: { label?: string }) { return <span>{label}</span>; }\n",
            'vue_code' => null,
            'sample_data' => '{"label": "Hot"}',
            'source_url' => 'https://stripe.com/pricing',
        ];
    }

    public function test_submit_component_stores_pending_and_alerts_admin()
    {
        Notification::fake();

        $user = User::factory()->create();
        $usage = Category::factory()->usage()->create();

        $this->actingAs($user)
            ->post('/dashboard/submissions', $this->validPayload($usage))
            ->assertRedirect(route('dashboard.submissions.index'))
            ->assertSessionHasNoErrors();

        $submission = ComponentSubmission::query()->sole();

        $this->assertSame($user->id, $submission->user_id);
        $this->assertSame('Pricing Badge', $submission->name);
        $this->assertSame(ComponentLevel::Block, $submission->level);
        $this->assertSame($usage->id, $submission->usage_category_id);
        $this->assertSame(SubmissionStatus::Pending, $submission->status);
        $this->assertSame(['label' => 'Hot'], $submission->sample_data);
        $this->assertSame('https://stripe.com/pricing', $submission->source_url);

        // The admin inbox gets the on-demand mail alert (mirrors ticket alerts).
        Notification::assertSentOnDemand(
            SubmissionCreatedNotification::class,
            fn (SubmissionCreatedNotification $notification, array $channels, object $notifiable): bool => $notifiable->routes['mail'] === config('mail.admin.address')
                && $notification->submission->is($submission),
        );
    }

    public function test_submit_validation_rules()
    {
        Notification::fake();

        $usage = Category::factory()->usage()->create();
        $industry = Category::factory()->industry()->create();

        // Each invalid post uses a fresh user so the NFR-10 throttle
        // (5 creations/minute) never masks validation with a 429.
        $postInvalid = function (array $overrides, string $errorKey) use ($usage): void {
            $this->actingAs(User::factory()->create())
                ->post('/dashboard/submissions', [...$this->validPayload($usage), ...$overrides])
                ->assertSessionHasErrors($errorKey);
        };

        // Unknown level / framework values are rejected.
        $postInvalid(['level' => 'molecule'], 'level');
        $postInvalid(['framework' => 'angular'], 'framework');

        // The category must be a usage category, not an industry.
        $postInvalid(['usage_category_id' => $industry->id], 'usage_category_id');

        // Code is required for the declared framework.
        $postInvalid(['react_code' => null], 'react_code');
        $postInvalid(['framework' => 'both', 'vue_code' => null], 'vue_code');

        // Sample data must be a JSON object.
        $postInvalid(['sample_data' => 'not json'], 'sample_data');
        $postInvalid(['sample_data' => '[1, 2, 3]'], 'sample_data');

        // Citation must be a URL.
        $postInvalid(['source_url' => 'stripe dot com'], 'source_url');

        $this->assertSame(0, ComponentSubmission::query()->count());
    }

    public function test_both_frameworks_accept_vue_and_react_code()
    {
        Notification::fake();

        $user = User::factory()->create();
        $usage = Category::factory()->usage()->create();

        $this->actingAs($user)
            ->post('/dashboard/submissions', [
                ...$this->validPayload($usage),
                'framework' => 'both',
                'vue_code' => "<script setup lang=\"ts\"></script>\n\n<template><span>Hot</span></template>\n",
            ])
            ->assertSessionHasNoErrors();

        $submission = ComponentSubmission::query()->sole();

        $this->assertNotNull($submission->react_code);
        $this->assertNotNull($submission->vue_code);
    }

    public function test_only_own_submissions_visible()
    {
        Notification::fake();

        $user = User::factory()->create();
        $other = User::factory()->create();

        $own = ComponentSubmission::factory()->create(['user_id' => $user->id]);
        ComponentSubmission::factory()->create(['user_id' => $other->id]);

        $this->get('/dashboard/submissions')->assertRedirect('/login');

        $this->actingAs($user)
            ->get('/dashboard/submissions')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard/submissions/index')
                ->has('submissions', 1)
                ->where('submissions.0.id', $own->id)
                ->where('submissions.0.status', 'pending')
            );
    }

    public function test_submit_rate_limited()
    {
        Notification::fake();

        $user = User::factory()->create();
        $usage = Category::factory()->usage()->create();

        // NFR-10: submission creation is throttled at 5 per minute per user.
        for ($i = 1; $i <= 5; $i++) {
            $this->actingAs($user)
                ->post('/dashboard/submissions', [...$this->validPayload($usage), 'name' => "Component {$i}"])
                ->assertRedirect();
        }

        $this->actingAs($user)
            ->post('/dashboard/submissions', [...$this->validPayload($usage), 'name' => 'One too many'])
            ->assertStatus(429);

        $this->assertSame(5, $user->componentSubmissions()->count());
    }
}
