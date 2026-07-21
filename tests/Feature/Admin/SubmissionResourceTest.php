<?php

namespace Tests\Feature\Admin;

use App\Enums\AccessLevel;
use App\Enums\ComponentLevel;
use App\Enums\ComponentStatus;
use App\Enums\SubmissionFramework;
use App\Enums\SubmissionStatus;
use App\Filament\Resources\ComponentSubmissions\ComponentSubmissionResource;
use App\Filament\Resources\ComponentSubmissions\Pages\ListComponentSubmissions;
use App\Filament\Resources\ComponentSubmissions\Pages\ViewComponentSubmission;
use App\Jobs\BuildComponentPreview;
use App\Models\Admin;
use App\Models\Category;
use App\Models\Component;
use App\Models\ComponentSubmission;
use App\Models\User;
use App\Notifications\SubmissionApprovedNotification;
use App\Notifications\SubmissionRejectedNotification;
use App\Services\Library\LibrarySync;
use App\Services\Submissions\SubmissionApprover;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use RuntimeException;
use Tests\Feature\Library\Concerns\BuildsLibraryFixtures;
use Tests\TestCase;

/**
 * Admin review pipeline for community submissions (task 5.3): the Manage
 * inbox (list + filters), Approve — which credits the submitter on an
 * in_review component and lands the source in the library tree for the
 * normal sync/preview pipeline — and Reject with a mailed review note.
 */
class SubmissionResourceTest extends TestCase
{
    use BuildsLibraryFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpLibraryFixtures();
    }

    protected function tearDown(): void
    {
        $this->tearDownLibraryFixtures();
        parent::tearDown();
    }

    public function test_admin_lists_and_filters_submissions()
    {
        $admin = Admin::factory()->create();

        $pending = ComponentSubmission::factory()->create(['level' => ComponentLevel::Block]);
        $approved = ComponentSubmission::factory()->approved()->create(['level' => ComponentLevel::Element]);
        $rejected = ComponentSubmission::factory()->rejected()->create(['level' => ComponentLevel::Block]);

        $this->actingAs($admin, 'admin');

        Livewire::test(ListComponentSubmissions::class)
            ->assertCanSeeTableRecords([$pending, $approved, $rejected])
            ->filterTable('status', SubmissionStatus::Pending->value)
            ->assertCanSeeTableRecords([$pending])
            ->assertCanNotSeeTableRecords([$approved, $rejected])
            ->resetTableFilters()
            ->filterTable('level', ComponentLevel::Element->value)
            ->assertCanSeeTableRecords([$approved])
            ->assertCanNotSeeTableRecords([$pending, $rejected])
            ->resetTableFilters()
            ->searchTable($rejected->name)
            ->assertCanSeeTableRecords([$rejected])
            ->assertCanNotSeeTableRecords([$pending, $approved]);
    }

    public function test_approve_creates_in_review_component_with_credit_and_citation()
    {
        Notification::fake();

        $admin = Admin::factory()->create();
        $user = User::factory()->create(['name' => 'Jane Maker']);
        $usage = $this->seedPricingCategory();

        $submission = ComponentSubmission::factory()->create([
            'user_id' => $user->id,
            'name' => 'Pricing Badge',
            'level' => ComponentLevel::Block,
            'usage_category_id' => $usage->id,
            'source_url' => 'https://stripe.com/pricing',
        ]);

        $this->actingAs($admin, 'admin');

        Livewire::test(ViewComponentSubmission::class, ['record' => $submission->id])
            ->callAction('approve')
            ->assertHasNoActionErrors()
            ->assertNotified('Submission approved');

        $submission->refresh();

        $this->assertSame(SubmissionStatus::Approved, $submission->status);
        $this->assertNotNull($submission->component_id);
        $this->assertNull($submission->review_note);

        $component = $submission->component;

        $this->assertSame('blocks/pricing-badge', $component->slug);
        $this->assertSame('Pricing Badge', $component->name);
        $this->assertSame(ComponentLevel::Block, $component->level);
        $this->assertSame($usage->id, $component->usage_category_id);
        $this->assertSame(ComponentStatus::InReview, $component->status);
        $this->assertSame(AccessLevel::Free, $component->access_level);
        $this->assertSame('1.0.0', $component->version);
        // PRD credit rule: the citation name credits the submitter; the URL
        // carries over the real-world reference link.
        $this->assertSame('Jane Maker', $component->source_name);
        $this->assertSame('https://stripe.com/pricing', $component->source_url);

        Notification::assertSentTo($user, SubmissionApprovedNotification::class);

        // Re-approving a reviewed submission is refused by the approver.
        try {
            app(SubmissionApprover::class)->approve($submission);
            $this->fail('Expected RuntimeException for a non-pending submission.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Only pending submissions can be approved.', $exception->getMessage());
        }
    }

    public function test_approve_writes_sync_discoverable_library_files_and_sync_owns_it()
    {
        Notification::fake();

        $admin = Admin::factory()->create();
        $usage = $this->seedPricingCategory();

        $submission = ComponentSubmission::factory()->create([
            'name' => 'Pricing Badge',
            'level' => ComponentLevel::Block,
            'usage_category_id' => $usage->id,
            'framework' => SubmissionFramework::Both,
            'react_code' => "export default function PricingBadge({ label = 'Hot' }: { label?: string }) { return <span className=\"badge\">{label}</span>; }\n",
            'vue_code' => "<script setup lang=\"ts\">\nwithDefaults(defineProps<{ label?: string }>(), { label: 'Hot' });\n</script>\n\n<template><span class=\"badge\">{{ label }}</span></template>\n",
            'sample_data' => [
                'label' => 'Hot',
                'featured' => true,
                'price' => 9.5,
                'tags' => ['new', 'sale'],
                'meta' => ['priority' => 1],
            ],
            'source_url' => 'https://stripe.com/pricing',
        ]);

        $this->actingAs($admin, 'admin');

        Livewire::test(ViewComponentSubmission::class, ['record' => $submission->id])
            ->callAction('approve')
            ->assertHasNoActionErrors();

        // React tree: index.tsx + params.json + data.json in the scanner's layout.
        $reactDir = $this->libraryRoot.'/react/src/components/blocks/pricing-badge';
        $this->assertFileExists($reactDir.'/index.tsx');
        $this->assertFileExists($reactDir.'/params.json');
        $this->assertFileExists($reactDir.'/data.json');

        $reactSource = (string) file_get_contents($reactDir.'/index.tsx');
        $this->assertStringStartsWith('/**', $reactSource);
        $this->assertStringContainsString('@component  pricing-badge', $reactSource);
        $this->assertStringContainsString('@level      block', $reactSource);
        $this->assertStringContainsString('@usage      pricing', $reactSource);
        $this->assertStringContainsString('@access     free', $reactSource);
        $this->assertStringContainsString('@source     https://stripe.com/pricing', $reactSource);
        $this->assertStringContainsString('export default function PricingBadge', $reactSource);

        // The params schema is inferred from the sample data types.
        $params = json_decode((string) file_get_contents($reactDir.'/params.json'), true);
        $this->assertSame('string', $params['label']['type']);
        $this->assertSame('boolean', $params['featured']['type']);
        $this->assertSame('number', $params['price']['type']);
        $this->assertSame('array', $params['tags']['type']);
        $this->assertSame('object', $params['meta']['type']);
        $this->assertSame('Hot', $params['label']['default']);

        $data = json_decode((string) file_get_contents($reactDir.'/data.json'), true);
        $this->assertSame($submission->sample_data, $data);

        // Vue tree: the docblock is injected inside the existing script tag.
        $vueDir = $this->libraryRoot.'/vue/src/components/blocks/pricing-badge';
        $this->assertFileExists($vueDir.'/index.vue');

        $vueSource = (string) file_get_contents($vueDir.'/index.vue');
        $this->assertStringContainsString("<script setup lang=\"ts\">\n/**", $vueSource);
        $this->assertStringContainsString('@component  pricing-badge', $vueSource);

        // A follow-up library:sync owns the component now: validates cleanly,
        // re-upserts the same row (no duplicate) and queues preview builds.
        Queue::fake();

        $component = $submission->refresh()->component;
        $result = app(LibrarySync::class)->run();

        $this->assertFalse($result->hasErrors());
        $this->assertSame(1, Component::query()->count());
        $this->assertSame(ComponentStatus::InReview, $component->refresh()->status);
        $this->assertSame($submission->user->name, $component->source_name);

        Queue::assertPushed(
            BuildComponentPreview::class,
            fn (BuildComponentPreview $job): bool => $job->componentId === $component->id && $job->frameworks === ['react', 'vue'],
        );
    }

    public function test_single_framework_submission_surfaces_missing_twin_on_sync()
    {
        Notification::fake();

        $admin = Admin::factory()->create();
        $usage = $this->seedPricingCategory();

        $submission = ComponentSubmission::factory()->create([
            'name' => 'Solo React Card',
            'level' => ComponentLevel::Element,
            'usage_category_id' => $usage->id,
            'framework' => SubmissionFramework::React,
            'vue_code' => null,
        ]);

        $this->actingAs($admin, 'admin');

        Livewire::test(ViewComponentSubmission::class, ['record' => $submission->id])
            ->callAction('approve')
            ->assertHasNoActionErrors();

        // Only the react tree received files.
        $this->assertFileExists($this->libraryRoot.'/react/src/components/elements/solo-react-card/index.tsx');
        $this->assertDirectoryDoesNotExist($this->libraryRoot.'/vue/src/components/elements/solo-react-card');

        // The sync flags the missing twin (dual-framework rule) but the
        // approved row stays in_review for the admin to author the twin.
        $result = app(LibrarySync::class)->run();

        $this->assertTrue($result->hasErrors());
        $this->assertContains('Missing vue twin', $result->errors['elements/solo-react-card']);
        $this->assertSame(ComponentStatus::InReview, $submission->refresh()->component->status);
    }

    public function test_reject_stores_note_and_notifies_submitter()
    {
        Notification::fake();

        $admin = Admin::factory()->create();
        $user = User::factory()->create();

        $submission = ComponentSubmission::factory()->create(['user_id' => $user->id]);

        $this->actingAs($admin, 'admin');

        // Reason is required.
        Livewire::test(ViewComponentSubmission::class, ['record' => $submission->id])
            ->callAction('reject', data: ['reason' => ''])
            ->assertHasActionErrors(['reason']);

        $this->assertSame(SubmissionStatus::Pending, $submission->fresh()->status);

        Livewire::test(ViewComponentSubmission::class, ['record' => $submission->id])
            ->callAction('reject', data: ['reason' => 'Copied markup — recreate instead of copying (license).'])
            ->assertHasNoActionErrors();

        $submission->refresh();

        $this->assertSame(SubmissionStatus::Rejected, $submission->status);
        $this->assertSame('Copied markup — recreate instead of copying (license).', $submission->review_note);
        $this->assertNull($submission->component_id);

        Notification::assertSentTo(
            $user,
            SubmissionRejectedNotification::class,
            fn (SubmissionRejectedNotification $notification): bool => $notification->submission->is($submission),
        );
    }

    public function test_non_admin_forbidden()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(ComponentSubmissionResource::getUrl('index'))
            ->assertRedirect('/admin/login');

        $submission = ComponentSubmission::factory()->create();

        $this->actingAs($user)
            ->get(ComponentSubmissionResource::getUrl('view', ['record' => $submission]))
            ->assertRedirect('/admin/login');
    }

    /**
     * The usage category the written annotation cites (`@usage pricing`) —
     * must exist for the follow-up library:sync taxonomy validation.
     */
    private function seedPricingCategory(): Category
    {
        return Category::factory()->usage()->create([
            'slug' => 'pricing',
            'name' => 'Pricing',
        ]);
    }
}
