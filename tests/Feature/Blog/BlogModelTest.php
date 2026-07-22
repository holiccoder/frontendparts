<?php

namespace Tests\Feature\Blog;

use App\Models\Blog;
use App\Models\BlogCategory;
use App\Models\BlogTag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_categories_tags_relations()
    {
        $post = Blog::factory()->published()->create();

        $categories = BlogCategory::factory()->count(2)->create();
        $tags = BlogTag::factory()->count(2)->create();

        $post->categories()->attach($categories);
        $post->tags()->attach($tags);

        $this->assertCount(2, $post->fresh()->categories);
        $this->assertCount(2, $post->fresh()->tags);
        $this->assertCount(1, $categories->first()->fresh()->posts);
        $this->assertCount(1, $tags->first()->fresh()->posts);

        $this->assertDatabaseHas('blog_category', [
            'blog_id' => $post->id,
            'blog_category_id' => $categories->first()->id,
        ]);
        $this->assertDatabaseHas('blog_tag', [
            'blog_id' => $post->id,
            'blog_tag_id' => $tags->first()->id,
        ]);
    }

    public function test_scheduled_posts_hidden_until_published_at()
    {
        $live = Blog::factory()->published()->create();
        $scheduled = Blog::factory()->scheduled()->create();
        $draftWithPastDate = Blog::factory()->create([
            'status' => 'draft',
            'published_at' => now()->subWeek(),
        ]);
        $publishedWithoutDate = Blog::factory()->create([
            'status' => 'published',
            'published_at' => null,
        ]);

        $visibleIds = Blog::query()->published()->pluck('id');

        $this->assertContains($live->id, $visibleIds);
        $this->assertNotContains($scheduled->id, $visibleIds);
        $this->assertNotContains($draftWithPastDate->id, $visibleIds);
        $this->assertNotContains($publishedWithoutDate->id, $visibleIds);

        // Once the scheduled moment passes, the post becomes visible on its own.
        $this->travelTo($scheduled->published_at->copy()->addMinute());

        $this->assertContains($scheduled->id, Blog::query()->published()->pluck('id'));
    }

    public function test_reading_time_computed()
    {
        $short = Blog::factory()->create(['body' => 'A handful of words only.']);
        $this->assertSame(1, $short->reading_time);

        // 200 words per minute, rounded up: 400 words → 2 minutes.
        $twoMinutes = Blog::factory()->create([
            'body' => implode(' ', array_fill(0, 400, 'word')),
        ]);
        $this->assertSame(2, $twoMinutes->reading_time);

        // 401 words still rounds up to 3 minutes…
        $roundedUp = Blog::factory()->create([
            'body' => implode(' ', array_fill(0, 401, 'word')),
        ]);
        $this->assertSame(3, $roundedUp->reading_time);

        // …and markdown/HTML markup never counts as words.
        $markup = Blog::factory()->create([
            'body' => "## Heading\n\n<strong>bold</strong> plain words here",
        ]);
        $this->assertSame(1, $markup->reading_time);
    }
}
