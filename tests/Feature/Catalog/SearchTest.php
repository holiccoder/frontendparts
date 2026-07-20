<?php

namespace Tests\Feature\Catalog;

use App\Models\Blog;
use App\Models\Category;
use App\Models\Component;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `/search?q=` (SPEC §15.1, FR-1.3): SSR site search over published
 * components (name, tags, usage + industry categories) and live blog
 * posts, grouped Components / Blog. Drafts, in-review components and
 * scheduled posts stay out; empty and zero-hit queries get an empty
 * state. The page itself is noindex.
 */
class SearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_matches_components_by_name_tag_category()
    {
        $usage = Category::factory()->usage()->create(['name' => 'Hero', 'slug' => 'hero']);
        $auroraUsage = Category::factory()->usage()->create(['name' => 'Aurora Banner', 'slug' => 'aurora-banner']);

        // Name match.
        $byName = Component::factory()->published()->create([
            'slug' => 'elements/aurora-01',
            'name' => 'Aurora Hero',
            'usage_category_id' => $usage->id,
        ]);

        // Tag match.
        $byTag = Component::factory()->published()->create([
            'slug' => 'elements/plain-01',
            'name' => 'Plain Card',
            'usage_category_id' => $usage->id,
        ]);
        $byTag->tags()->attach(Tag::factory()->create(['name' => 'aurora', 'slug' => 'aurora']));

        // Usage-category name match.
        $byUsage = Component::factory()->published()->create([
            'slug' => 'elements/plain-02',
            'name' => 'Plain Panel',
            'usage_category_id' => $auroraUsage->id,
        ]);

        // Industry-category name match.
        $byIndustry = Component::factory()->published()->create([
            'slug' => 'elements/plain-03',
            'name' => 'Plain Footer',
            'usage_category_id' => $usage->id,
        ]);
        $industry = Category::factory()->industry()->create(['name' => 'Aurora Health', 'slug' => 'aurora-health']);
        $industry->components()->attach($byIndustry);

        // A published component that matches nothing must not appear.
        Component::factory()->published()->create([
            'slug' => 'elements/zebra-01',
            'name' => 'Zebra Table',
            'usage_category_id' => $usage->id,
        ]);

        $response = $this->get('/search?q=aurora')
            ->assertOk()
            ->assertHeaderMissing('X-SSR-Skipped');

        $page = $response->viewData('page');
        $props = $page['props'];

        $this->assertSame('search', $page['component']);
        $this->assertSame('aurora', $props['query']);

        $ids = array_column($props['components'], 'id');

        $this->assertContains($byName->id, $ids);
        $this->assertContains($byTag->id, $ids);
        $this->assertContains($byUsage->id, $ids);
        $this->assertContains($byIndustry->id, $ids);
        $this->assertCount(4, $ids);

        // Every hit carries the public component URL.
        foreach ($props['components'] as $component) {
            $this->assertStringStartsWith(url('/components/'), $component['url']);
        }
    }

    public function test_matches_blog_posts()
    {
        Blog::factory()->published()->create([
            'title' => 'Designing aurora dashboards',
            'slug' => 'designing-aurora-dashboards',
        ]);

        Blog::factory()->published()->create([
            'title' => 'Unrelated teardown',
            'slug' => 'unrelated-teardown',
            'excerpt' => 'A post with aurora in the excerpt only.',
        ]);

        Blog::factory()->published()->create([
            'title' => 'Nothing here',
            'slug' => 'nothing-here',
        ]);

        $props = $this->get('/search?q=aurora')->assertOk()->viewData('page')['props'];

        $slugs = array_column($props['posts'], 'slug');

        $this->assertContains('designing-aurora-dashboards', $slugs);
        $this->assertContains('unrelated-teardown', $slugs);
        $this->assertNotContains('nothing-here', $slugs);

        // Every hit carries the public blog URL.
        foreach ($props['posts'] as $post) {
            $this->assertStringStartsWith(url('/blog/'), $post['url']);
        }
    }

    public function test_drafts_excluded()
    {
        $usage = Category::factory()->usage()->create(['name' => 'Hero', 'slug' => 'hero']);

        Component::factory()->published()->create([
            'slug' => 'elements/aurora-live-01',
            'name' => 'Aurora Live',
            'usage_category_id' => $usage->id,
        ]);

        Component::factory()->draft()->create([
            'slug' => 'elements/aurora-draft-01',
            'name' => 'Aurora Draft',
            'usage_category_id' => $usage->id,
        ]);

        Component::factory()->inReview()->create([
            'slug' => 'elements/aurora-review-01',
            'name' => 'Aurora Review',
            'usage_category_id' => $usage->id,
        ]);

        Blog::factory()->published()->create(['title' => 'Aurora live post', 'slug' => 'aurora-live-post']);
        Blog::factory()->draft()->create(['title' => 'Aurora draft post', 'slug' => 'aurora-draft-post']);
        Blog::factory()->scheduled()->create(['title' => 'Aurora scheduled post', 'slug' => 'aurora-scheduled-post']);

        $props = $this->get('/search?q=aurora')->assertOk()->viewData('page')['props'];

        $this->assertSame(['Aurora Live'], array_column($props['components'], 'name'));
        $this->assertSame(['Aurora live post'], array_column($props['posts'], 'title'));
    }

    public function test_empty_state()
    {
        $usage = Category::factory()->usage()->create(['name' => 'Hero', 'slug' => 'hero']);

        Component::factory()->published()->create([
            'slug' => 'elements/zebra-01',
            'name' => 'Zebra Table',
            'usage_category_id' => $usage->id,
        ]);
        Blog::factory()->published()->create(['title' => 'Zebra post', 'slug' => 'zebra-post']);

        // Zero-hit query: both groups empty, query echoed back.
        $props = $this->get('/search?q=zzz-no-such-term')->assertOk()->viewData('page')['props'];

        $this->assertSame('zzz-no-such-term', $props['query']);
        $this->assertSame([], $props['components']);
        $this->assertSame([], $props['posts']);

        // Missing/empty query: handled gracefully with no results.
        $props = $this->get('/search')->assertOk()->viewData('page')['props'];

        $this->assertSame('', $props['query']);
        $this->assertSame([], $props['components']);
        $this->assertSame([], $props['posts']);

        $props = $this->get('/search?q=')->assertOk()->viewData('page')['props'];

        $this->assertSame('', $props['query']);
        $this->assertSame([], $props['components']);
    }

    public function test_search_page_is_noindex()
    {
        $meta = $this->get('/search?q=hero')->assertOk()->viewData('page')['props']['meta'];

        $this->assertSame('noindex', $meta['robots']);
    }
}
