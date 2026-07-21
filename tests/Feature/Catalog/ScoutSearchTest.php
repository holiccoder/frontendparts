<?php

namespace Tests\Feature\Catalog;

use App\Models\Blog;
use App\Models\Category;
use App\Models\Component;
use App\Models\DocsPage;
use App\Models\Tag;
use App\Services\Catalog\SiteSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Meilisearch\Client as MeilisearchClient;
use Tests\TestCase;

/**
 * Scout swap (Phase 5.1 — SPEC §13.2, FR-1.3): Component, Blog and DocsPage
 * are Scout-searchable; only live records are searchable (published
 * components, live posts); production wiring is SCOUT_DRIVER=meilisearch
 * plus host/key. Tests run on the collection engine (no Meilisearch server
 * in this environment); engine-specific behavior such as typo tolerance
 * skips gracefully.
 */
class ScoutSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_published_components_are_searchable()
    {
        $usage = Category::factory()->usage()->create(['name' => 'Hero', 'slug' => 'hero']);

        $published = Component::factory()->published()->create([
            'slug' => 'elements/aurora-live-01',
            'name' => 'Aurora Live',
            'usage_category_id' => $usage->id,
        ]);
        $draft = Component::factory()->draft()->create([
            'slug' => 'elements/aurora-draft-01',
            'name' => 'Aurora Draft',
            'usage_category_id' => $usage->id,
        ]);
        $inReview = Component::factory()->inReview()->create([
            'slug' => 'elements/aurora-review-01',
            'name' => 'Aurora Review',
            'usage_category_id' => $usage->id,
        ]);

        $this->assertTrue($published->shouldBeSearchable());
        $this->assertFalse($draft->shouldBeSearchable());
        $this->assertFalse($inReview->shouldBeSearchable());

        // The engine honors the same gate at query time.
        $names = Component::search('aurora')->get()->pluck('name')->all();

        $this->assertSame(['Aurora Live'], $names);
    }

    public function test_only_live_blog_posts_are_searchable()
    {
        $live = Blog::factory()->published()->create(['title' => 'Aurora live post']);
        $draft = Blog::factory()->draft()->create(['title' => 'Aurora draft post']);
        $scheduled = Blog::factory()->scheduled()->create(['title' => 'Aurora scheduled post']);

        $this->assertTrue($live->shouldBeSearchable());
        $this->assertFalse($draft->shouldBeSearchable());
        $this->assertFalse($scheduled->shouldBeSearchable());

        $titles = Blog::search('aurora')->get()->pluck('title')->all();

        $this->assertSame(['Aurora live post'], $titles);
    }

    public function test_component_searchable_payload_flattens_relations()
    {
        $usage = Category::factory()->usage()->create(['name' => 'Hero Section', 'slug' => 'hero']);
        $industry = Category::factory()->industry()->create(['name' => 'SaaS', 'slug' => 'saas']);

        $component = Component::factory()->published()->create([
            'slug' => 'elements/aurora-01',
            'name' => 'Aurora Hero',
            'usage_category_id' => $usage->id,
        ]);
        $component->tags()->attach(Tag::factory()->create(['name' => 'gradient', 'slug' => 'gradient']));
        $industry->components()->attach($component);

        // The flattened payload is the Meilisearch document: relation names
        // must be present so catalog search matches tags and categories.
        $payload = $component->fresh()->toSearchableArray();

        $this->assertSame('Aurora Hero', $payload['name']);
        $this->assertSame('elements/aurora-01', $payload['slug']);
        $this->assertSame(['gradient'], $payload['tags']);
        $this->assertSame('Hero Section', $payload['usage_category']);
        $this->assertSame(['SaaS'], $payload['industries']);
    }

    public function test_site_search_matches_relations_through_the_scout_engine()
    {
        $usage = Category::factory()->usage()->create(['name' => 'Hero', 'slug' => 'hero']);

        $component = Component::factory()->published()->create([
            'slug' => 'elements/plain-01',
            'name' => 'Plain Card',
            'usage_category_id' => $usage->id,
        ]);
        $component->tags()->attach(Tag::factory()->create(['name' => 'aurora', 'slug' => 'aurora']));

        $search = app(SiteSearch::class);

        // Only the Scout payload carries the tag name — a plain column
        // search over components could never match this.
        $this->assertSame([$component->id], $search->components('aurora')->pluck('id')->all());
        $this->assertSame([], $search->components('zzz-no-such-term')->pluck('id')->all());
    }

    public function test_scout_config_defaults_and_meilisearch_wiring()
    {
        // Local/tests default: no infrastructure needed.
        $this->assertSame('collection', config('scout.driver'));

        // Production: host/key come straight from the environment.
        $this->assertSame('http://localhost:7700', config('scout.meilisearch.host'));
        $this->assertArrayHasKey('key', config('scout.meilisearch'));
        $this->assertArrayHasKey(Component::class, config('scout.meilisearch.index-settings'));
        $this->assertArrayHasKey(Blog::class, config('scout.meilisearch.index-settings'));
        $this->assertArrayHasKey(DocsPage::class, config('scout.meilisearch.index-settings'));

        // The client resolves from config; construction makes no network
        // call, so this proves wiring without a running Meilisearch.
        config()->set('scout.meilisearch.host', 'http://meilisearch.internal:7700');
        app()->forgetInstance(MeilisearchClient::class);

        $this->assertInstanceOf(MeilisearchClient::class, app(MeilisearchClient::class));
    }

    public function test_scout_import_command_imports_all_searchable_models()
    {
        $usage = Category::factory()->usage()->create(['name' => 'Hero', 'slug' => 'hero']);
        Component::factory()->published()->create([
            'slug' => 'elements/aurora-01',
            'name' => 'Aurora Hero',
            'usage_category_id' => $usage->id,
        ]);
        Blog::factory()->published()->create(['title' => 'Aurora post']);

        foreach ([Component::class, Blog::class, DocsPage::class] as $model) {
            $this->artisan('scout:import', ['model' => $model])->assertExitCode(0);
        }
    }

    public function test_typo_tolerance_is_a_meilisearch_engine_feature()
    {
        if (config('scout.driver') !== 'meilisearch') {
            $this->markTestSkipped('Typo tolerance requires the Meilisearch engine; the collection engine matches substrings only.');
        }

        $usage = Category::factory()->usage()->create(['name' => 'Hero', 'slug' => 'hero']);
        $component = Component::factory()->published()->create([
            'slug' => 'elements/pricing-01',
            'name' => 'Pricing Table',
            'usage_category_id' => $usage->id,
        ]);

        $this->assertContains($component->id, Component::search('prcing')->get()->pluck('id'));
    }
}
