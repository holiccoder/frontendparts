<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Blogs\Pages\CreateBlog;
use App\Filament\Resources\Blogs\Pages\EditBlog;
use App\Models\Admin;
use App\Models\Blog;
use App\Models\BlogCategory;
use App\Models\BlogTag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BlogResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_save_with_all_extended_fields()
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin');

        $author = User::factory()->create();
        $category = BlogCategory::factory()->create();
        $tag = BlogTag::factory()->create();
        $publishedAt = now()->subDay();

        Livewire::test(CreateBlog::class)
            ->fillForm([
                'user_id' => $author->id,
                'title' => '10 SaaS Pricing Page Designs',
                'slug' => '10-saas-pricing-page-designs',
                'excerpt' => 'The best SaaS pricing pages, recreated.',
                'body' => "## Intro\n\nLong-form body copy.",
                'status' => 'published',
                'published_at' => $publishedAt->toDateTimeString(),
                'categories' => [$category->id],
                'tags' => [$tag->id],
                'meta_title' => 'SaaS pricing pages, recreated',
                'meta_description' => 'A teardown of ten SaaS pricing pages.',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $post = Blog::query()->where('slug', '10-saas-pricing-page-designs')->sole();

        $this->assertSame($author->id, $post->user_id);
        $this->assertSame('published', $post->status);
        $this->assertSame('SaaS pricing pages, recreated', $post->meta_title);
        $this->assertSame('A teardown of ten SaaS pricing pages.', $post->meta_description);
        $this->assertEquals($publishedAt->toDateTimeString(), $post->published_at->toDateTimeString());

        $this->assertSame([$category->id], $post->categories->modelKeys());
        $this->assertSame([$tag->id], $post->tags->modelKeys());
    }

    public function test_taxonomy_pickers_persist()
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin');

        $post = Blog::factory()->create();
        $categories = BlogCategory::factory()->count(2)->create();
        $tags = BlogTag::factory()->count(2)->create();

        Livewire::test(EditBlog::class, ['record' => $post->id])
            ->fillForm([
                'categories' => $categories->modelKeys(),
                'tags' => $tags->modelKeys(),
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        // The pickers sync the pivots: previously attached rows are replaced.
        $this->assertEqualsCanonicalizing($categories->modelKeys(), $post->fresh()->categories->modelKeys());
        $this->assertEqualsCanonicalizing($tags->modelKeys(), $post->fresh()->tags->modelKeys());
    }
}
