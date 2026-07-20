<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Blogs\Pages\CreateBlog;
use App\Filament\Resources\Blogs\Pages\EditBlog;
use App\Models\Admin;
use App\Models\Blog;
use App\Models\BlogCategory;
use App\Models\BlogTag;
use App\Models\Component;
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
        $component = Component::factory()->create();
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
                'relatedComponents' => [$component->id],
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
        $this->assertSame([$component->id], $post->relatedComponents->modelKeys());
    }

    public function test_related_components_picker_persists()
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin');

        $post = Blog::factory()->create();
        $keep = Component::factory()->create();
        $components = Component::factory()->count(2)->create();

        $post->relatedComponents()->attach($keep);

        Livewire::test(EditBlog::class, ['record' => $post->id])
            ->fillForm([
                'relatedComponents' => $components->modelKeys(),
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        // The picker syncs the pivot: previously attached rows are replaced.
        $this->assertEqualsCanonicalizing(
            $components->modelKeys(),
            $post->fresh()->relatedComponents->modelKeys(),
        );
    }
}
