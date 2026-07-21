<?php

namespace Tests\Feature\Admin;

use App\Enums\AccessLevel;
use App\Enums\ComponentLevel;
use App\Enums\ComponentStatus;
use App\Filament\Resources\Components\Pages\ViewComponent;
use App\Jobs\GenerateComponentVariant;
use App\Models\Admin;
use App\Models\Category;
use App\Models\Component;
use App\Models\Tag;
use App\Services\Ai\Agents\ComponentVariantAgent;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\Feature\Library\Concerns\BuildsLibraryFixtures;
use Tests\TestCase;

/**
 * AI component variants (task 5.4, features.ai_variants): the "Generate
 * variant" action on ComponentResource queues the generation job (panel
 * returns immediately). The job writes the variant into both library trees,
 * files it as a new in-review component linked to the original
 * (variant_of), credits it as AI-generated, and notifies the admin —
 * success or danger. Nothing is ever auto-published.
 */
class ComponentVariantActionTest extends TestCase
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

    public function test_action_hidden_when_flag_off()
    {
        $admin = Admin::factory()->create();
        $component = Component::factory()->published()->create();

        $this->actingAs($admin, 'admin');

        Livewire::test(ViewComponent::class, ['record' => $component->id])
            ->assertActionHidden('generate-variant');
    }

    public function test_action_queues_generation_job_when_flag_on()
    {
        Queue::fake();

        app(Settings::class)->set('features.ai_variants', true);

        $admin = Admin::factory()->create();
        $component = Component::factory()->published()->create();

        $this->actingAs($admin, 'admin');

        Livewire::test(ViewComponent::class, ['record' => $component->id])
            ->assertActionVisible('generate-variant')
            ->callAction('generate-variant')
            ->assertNotified('Variant generation queued');

        Queue::assertPushed(
            GenerateComponentVariant::class,
            fn (GenerateComponentVariant $job): bool => $job->componentId === $component->id && $job->adminId === $admin->id,
        );
    }

    public function test_job_creates_in_review_variant_with_linkage_and_citation()
    {
        $this->enableAiVariants();

        $admin = Admin::factory()->create();
        $usage = $this->seedPricingCategory();
        $saas = Category::factory()->industry()->create(['name' => 'SaaS', 'slug' => 'saas']);
        $tag = Tag::factory()->create(['name' => 'Stripe', 'slug' => 'stripe']);

        $this->libraryComponent('blocks/pricing-card', ['usage' => 'pricing', 'industries' => 'saas', 'tags' => 'stripe']);

        $original = Component::factory()->published()->block()->create([
            'slug' => 'blocks/pricing-card',
            'name' => 'Pricing Card',
            'usage_category_id' => $usage->id,
        ]);
        $original->industries()->attach($saas);
        $original->tags()->attach($tag);

        ComponentVariantAgent::fake([[
            'name' => 'Pricing Card Minimal',
            'summary' => 'Whitespace-first flat restyle with a single accent color.',
            'react_code' => "export default function PricingCardMinimal() { return <section className=\"flat\" />; }\n",
            'vue_code' => "<script setup lang=\"ts\">\n</script>\n\n<template><section class=\"flat\" /></template>\n",
        ]]);

        GenerateComponentVariant::dispatchSync($original->id, $admin->id);

        $variant = Component::query()->where('variant_of', $original->id)->sole();

        $this->assertSame('blocks/pricing-card-minimal', $variant->slug);
        $this->assertSame('Pricing Card Minimal', $variant->name);
        $this->assertSame(ComponentLevel::Block, $variant->level);
        $this->assertSame($usage->id, $variant->usage_category_id);
        $this->assertSame(ComponentStatus::InReview, $variant->status);
        $this->assertSame(AccessLevel::Free, $variant->access_level);
        $this->assertSame('1.0.0', $variant->version);
        // Citation convention: source_name marks the component AI-generated.
        $this->assertSame('AI-generated variant of Pricing Card', $variant->source_name);
        $this->assertNull($variant->source_url);

        // The variant inherits the original's taxonomy relations.
        $this->assertSame([$saas->id], $variant->industries()->pluck('categories.id')->all());
        $this->assertSame([$tag->id], $variant->tags()->pluck('tags.id')->all());

        // The original is untouched.
        $original->refresh();
        $this->assertSame('Pricing Card', $original->name);
        $this->assertSame(ComponentStatus::Published, $original->status);
        $this->assertNull($original->source_name);
        $this->assertNull($original->variant_of);

        // Library files landed in both trees with the sync annotation.
        $reactDir = $this->libraryRoot.'/react/src/components/blocks/pricing-card-minimal';
        $vueDir = $this->libraryRoot.'/vue/src/components/blocks/pricing-card-minimal';

        $reactSource = (string) file_get_contents($reactDir.'/index.tsx');
        $this->assertStringContainsString('@component  pricing-card-minimal', $reactSource);
        $this->assertStringContainsString('@level      block', $reactSource);
        $this->assertStringContainsString('@usage      pricing', $reactSource);
        $this->assertStringContainsString('@industries saas', $reactSource);
        $this->assertStringContainsString('@tags       stripe', $reactSource);
        $this->assertStringContainsString('PricingCardMinimal', $reactSource);

        $this->assertFileExists($vueDir.'/index.vue');

        // The params contract is copied from the original.
        $this->assertSame(
            json_decode((string) file_get_contents($this->libraryRoot.'/react/src/components/blocks/pricing-card/params.json'), true),
            json_decode((string) file_get_contents($reactDir.'/params.json'), true),
        );

        // The admin got the success notification with the model's summary.
        $this->assertAdminNotified($admin, 'AI variant ready for review', 'Whitespace-first flat restyle');
    }

    public function test_job_failure_leaves_no_component_and_notifies_danger()
    {
        $this->enableAiVariants();

        $admin = Admin::factory()->create();
        $usage = $this->seedPricingCategory();

        $this->libraryComponent('blocks/pricing-card', ['usage' => 'pricing']);

        $original = Component::factory()->published()->block()->create([
            'slug' => 'blocks/pricing-card',
            'name' => 'Pricing Card',
            'usage_category_id' => $usage->id,
        ]);

        // The fake gateway throws when prompted without a fake response.
        ComponentVariantAgent::fake()->preventStrayPrompts();

        GenerateComponentVariant::dispatchSync($original->id, $admin->id);

        // No variant row, no library folders, original untouched.
        $this->assertSame(1, Component::query()->count());
        $this->assertSame(
            ['pricing-card'],
            array_map('basename', glob($this->libraryRoot.'/react/src/components/blocks/*') ?: []),
        );
        $this->assertSame(ComponentStatus::Published, $original->refresh()->status);

        $this->assertAdminNotified($admin, 'AI variant generation failed', 'Pricing Card');
    }

    public function test_job_fails_cleanly_when_source_is_not_checked_out()
    {
        $this->enableAiVariants();

        $admin = Admin::factory()->create();

        // A component row whose library files do not exist locally.
        $original = Component::factory()->published()->block()->create([
            'slug' => 'blocks/ghost-card',
            'name' => 'Ghost Card',
        ]);

        ComponentVariantAgent::fake();

        GenerateComponentVariant::dispatchSync($original->id, $admin->id);

        // No AI call happened (source check precedes prompting), nothing created.
        ComponentVariantAgent::assertNeverPrompted();
        $this->assertSame(1, Component::query()->count());

        $this->assertAdminNotified($admin, 'AI variant generation failed', 'Ghost Card');
    }

    private function enableAiVariants(): void
    {
        app(Settings::class)->set('features.ai_variants', true);
        config()->set('ai.providers.openai.key', 'test-key');
    }

    private function seedPricingCategory(): Category
    {
        return Category::factory()->usage()->create([
            'slug' => 'pricing',
            'name' => 'Pricing',
        ]);
    }

    /**
     * Assert the admin received a database notification whose title/body
     * carry the expected fragments.
     */
    private function assertAdminNotified(Admin $admin, string $title, string $bodyFragment): void
    {
        $notifications = $admin->notifications()->get();

        $this->assertCount(1, $notifications);

        $data = $notifications->first()->data;

        $this->assertSame($title, $data['title']);
        $this->assertStringContainsString($bodyFragment, (string) $data['body']);
    }
}
