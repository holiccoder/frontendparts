<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\Component;
use App\Services\Ai\Agents\CatalogSearchIntentAgent;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AI-assisted catalog search (task 5.4, features.ai_search): `/search?q=…&ai=1`
 * sends the natural-language query through the AiGateway once per submit,
 * scopes the Scout component results by the returned intent and labels them
 * "AI-assisted". Flag off, missing key, rate limit or provider failure all
 * fall back to plain results — never an error page.
 */
class AiSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_flag_off_ignores_ai_param_and_never_prompts()
    {
        CatalogSearchIntentAgent::fake();

        $usage = Category::factory()->usage()->create(['name' => 'Hero', 'slug' => 'hero']);

        $component = Component::factory()->published()->create([
            'slug' => 'elements/aurora-01',
            'name' => 'Aurora Hero',
            'usage_category_id' => $usage->id,
        ]);

        $props = $this->get('/search?q=aurora&ai=1')->assertOk()->viewData('page')['props'];

        $this->assertFalse($props['ai']['available']);
        $this->assertFalse($props['ai']['active']);
        $this->assertContains($component->id, array_column($props['components'], 'id'));

        CatalogSearchIntentAgent::assertNeverPrompted();
    }

    public function test_ai_mode_scopes_results_by_usage_and_level_filters()
    {
        $this->enableAiSearch();

        $hero = Category::factory()->usage()->create(['name' => 'Hero', 'slug' => 'hero']);
        $footer = Category::factory()->usage()->create(['name' => 'Footer', 'slug' => 'footer']);

        $match = Component::factory()->published()->element()->create([
            'slug' => 'elements/stat-card-01',
            'name' => 'Stat Card',
            'usage_category_id' => $hero->id,
        ]);

        // Keyword matches but the level filter excludes it.
        Component::factory()->published()->section()->create([
            'slug' => 'sections/stat-showcase-01',
            'name' => 'Stat Showcase',
            'usage_category_id' => $hero->id,
        ]);

        // Keyword matches but the usage filter excludes it.
        Component::factory()->published()->element()->create([
            'slug' => 'elements/stat-strip-01',
            'name' => 'Stat Strip',
            'usage_category_id' => $footer->id,
        ]);

        CatalogSearchIntentAgent::fake([[
            'keywords' => 'stat',
            'usage_categories' => ['hero'],
            'industries' => [],
            'levels' => ['element'],
        ]]);

        $props = $this->get('/search?q=stats for the top of my dashboard&ai=1')
            ->assertOk()
            ->assertHeaderMissing('X-SSR-Skipped')
            ->viewData('page')['props'];

        $this->assertTrue($props['ai']['available']);
        $this->assertTrue($props['ai']['requested']);
        $this->assertTrue($props['ai']['active']);
        $this->assertFalse($props['ai']['limited']);
        $this->assertSame('stat', $props['ai']['keywords']);
        $this->assertSame(['Hero', 'Element'], $props['ai']['filters']);

        $this->assertSame([$match->id], array_column($props['components'], 'id'));

        CatalogSearchIntentAgent::assertPrompted('stats for the top of my dashboard');
    }

    public function test_ai_mode_scopes_results_by_industry_filter()
    {
        $this->enableAiSearch();

        $usage = Category::factory()->usage()->create(['name' => 'Pricing', 'slug' => 'pricing']);
        $saas = Category::factory()->industry()->create(['name' => 'SaaS', 'slug' => 'saas']);
        $retail = Category::factory()->industry()->create(['name' => 'Retail', 'slug' => 'retail']);

        $match = Component::factory()->published()->block()->create([
            'slug' => 'blocks/pricing-table-01',
            'name' => 'Pricing Table',
            'usage_category_id' => $usage->id,
        ]);
        $match->industries()->attach($saas);

        $other = Component::factory()->published()->block()->create([
            'slug' => 'blocks/pricing-grid-01',
            'name' => 'Pricing Grid',
            'usage_category_id' => $usage->id,
        ]);
        $other->industries()->attach($retail);

        CatalogSearchIntentAgent::fake([[
            'keywords' => 'pricing',
            'usage_categories' => [],
            'industries' => ['saas'],
            'levels' => [],
        ]]);

        $props = $this->get('/search?q=pricing blocks for a saas landing page&ai=1')->assertOk()->viewData('page')['props'];

        $this->assertTrue($props['ai']['active']);
        $this->assertSame(['SaaS'], $props['ai']['filters']);
        $this->assertSame([$match->id], array_column($props['components'], 'id'));
    }

    public function test_hallucinated_filters_are_dropped_and_plain_search_runs()
    {
        $this->enableAiSearch();

        $usage = Category::factory()->usage()->create(['name' => 'Hero', 'slug' => 'hero']);

        $component = Component::factory()->published()->create([
            'slug' => 'elements/stat-card-01',
            'name' => 'Stat Card',
            'usage_category_id' => $usage->id,
        ]);

        // Every slug the model returned is unknown and keywords are empty:
        // the intent carries no usable signal → plain search fallback.
        CatalogSearchIntentAgent::fake([[
            'keywords' => '',
            'usage_categories' => ['does-not-exist'],
            'industries' => ['also-invented'],
            'levels' => [],
        ]]);

        $props = $this->get('/search?q=stat&ai=1')->assertOk()->viewData('page')['props'];

        $this->assertFalse($props['ai']['active']);
        $this->assertContains($component->id, array_column($props['components'], 'id'));
    }

    public function test_missing_provider_key_skips_ai_and_never_prompts()
    {
        app(Settings::class)->set('features.ai_search', true);
        config()->set('ai.providers.openai.key', null);

        CatalogSearchIntentAgent::fake();

        $usage = Category::factory()->usage()->create(['name' => 'Hero', 'slug' => 'hero']);

        $component = Component::factory()->published()->create([
            'slug' => 'elements/aurora-01',
            'name' => 'Aurora Hero',
            'usage_category_id' => $usage->id,
        ]);

        $props = $this->get('/search?q=aurora&ai=1')->assertOk()->viewData('page')['props'];

        $this->assertTrue($props['ai']['available']);
        $this->assertFalse($props['ai']['active']);
        $this->assertContains($component->id, array_column($props['components'], 'id'));

        CatalogSearchIntentAgent::assertNeverPrompted();
    }

    public function test_provider_failure_falls_back_to_plain_results()
    {
        $this->enableAiSearch();

        $usage = Category::factory()->usage()->create(['name' => 'Hero', 'slug' => 'hero']);

        $component = Component::factory()->published()->create([
            'slug' => 'elements/aurora-01',
            'name' => 'Aurora Hero',
            'usage_category_id' => $usage->id,
        ]);

        // No fake responses registered → the fake gateway throws on prompt.
        CatalogSearchIntentAgent::fake()->preventStrayPrompts();

        $props = $this->get('/search?q=aurora&ai=1')->assertOk()->viewData('page')['props'];

        $this->assertFalse($props['ai']['active']);
        $this->assertContains($component->id, array_column($props['components'], 'id'));
    }

    public function test_ai_mode_is_rate_limited_per_ip()
    {
        $this->enableAiSearch();
        config()->set('ai.features.search_rate_limit', 2);

        $usage = Category::factory()->usage()->create(['name' => 'Hero', 'slug' => 'hero']);

        Component::factory()->published()->create([
            'slug' => 'elements/stat-card-01',
            'name' => 'Stat Card',
            'usage_category_id' => $usage->id,
        ]);

        CatalogSearchIntentAgent::fake([[
            'keywords' => 'stat',
            'usage_categories' => ['hero'],
            'industries' => [],
            'levels' => [],
        ]]);

        $first = $this->get('/search?q=stat please&ai=1')->assertOk()->viewData('page')['props'];
        $second = $this->get('/search?q=stat again&ai=1')->assertOk()->viewData('page')['props'];
        $third = $this->get('/search?q=stat once more&ai=1')->assertOk()->viewData('page')['props'];

        $this->assertTrue($first['ai']['active']);
        $this->assertTrue($second['ai']['active']);

        // Over the limit: plain results, marked limited, still a 200 page.
        $this->assertFalse($third['ai']['active']);
        $this->assertTrue($third['ai']['limited']);
    }

    private function enableAiSearch(): void
    {
        app(Settings::class)->set('features.ai_search', true);
        config()->set('ai.providers.openai.key', 'test-key');
    }
}
