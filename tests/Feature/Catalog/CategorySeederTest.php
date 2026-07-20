<?php

namespace Tests\Feature\Catalog;

use App\Enums\CategoryType;
use App\Models\Category;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CategorySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_exactly_12_industries()
    {
        $this->seed(CategorySeeder::class);

        $industries = Category::query()
            ->where('type', CategoryType::Industry)
            ->orderBy('sort_order')
            ->get();

        $this->assertCount(12, $industries);
        $this->assertSame([
            'SaaS & Software',
            'Ecommerce & Retail',
            'Fintech & Finance',
            'Healthcare & Medical',
            'Education',
            'Real Estate',
            'Food & Restaurant',
            'Travel & Hospitality',
            'Agency & Portfolio',
            'Crypto & Web3',
            'Fitness & Wellness',
            'Events & Entertainment',
        ], $industries->pluck('name')->all());
        $this->assertTrue($industries->every(fn (Category $category): bool => $category->zone === null));
    }

    public function test_seeds_exactly_32_usage_patterns_with_zones()
    {
        $this->seed(CategorySeeder::class);

        $usage = Category::query()
            ->where('type', CategoryType::Usage)
            ->orderBy('sort_order')
            ->get();

        // Pins the full zone map from the SPEC §4.2 table (32 patterns).
        $expected = [
            'Navigation' => ['Navbar', 'Footer', 'Sidebar', 'Mega Menu', 'Breadcrumb'],
            'Opening' => ['Hero', 'Logo Cloud', 'Announcement Banner'],
            'Content' => ['Feature Grid', 'About Section', 'Gallery', 'Team', 'Blog List', 'Blog Article', 'Stats'],
            'Social proof' => ['Testimonial', 'Reviews & Ratings', 'Case Study'],
            'Conversion' => ['Pricing', 'CTA', 'FAQ', 'Contact Form', 'Newsletter', 'Auth Forms'],
            'Commerce' => ['Product Card', 'Product Detail', 'Cart & Checkout'],
            'App UI' => ['Dashboard Card', 'Data Table', 'Modal & Dialog', 'Alerts & Toast', 'Empty State & 404'],
        ];

        $this->assertCount(32, $usage);

        $grouped = $usage
            ->groupBy('zone')
            ->map(fn ($categories) => $categories->pluck('name')->all())
            ->all();

        $this->assertSame($expected, $grouped);
    }

    public function test_slugs_unique_per_type()
    {
        $this->seed(CategorySeeder::class);

        $duplicateCount = DB::table('categories')
            ->select('type', 'slug')
            ->groupBy('type', 'slug')
            ->havingRaw('COUNT(*) > 1')
            ->count();

        $this->assertSame(0, $duplicateCount);

        $slugs = Category::query()->pluck('slug');

        $this->assertTrue($slugs->every(
            fn (string $slug): bool => preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug) === 1
        ));
    }

    public function test_seeder_is_idempotent()
    {
        $this->seed(CategorySeeder::class);
        $this->seed(CategorySeeder::class);

        $this->assertSame(12, Category::query()->where('type', CategoryType::Industry)->count());
        $this->assertSame(32, Category::query()->where('type', CategoryType::Usage)->count());
    }
}
