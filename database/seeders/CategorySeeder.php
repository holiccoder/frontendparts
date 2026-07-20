<?php

namespace Database\Seeders;

use App\Enums\CategoryType;
use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * The 12 industries (SPEC §4.1), verbatim.
     *
     * @var list<string>
     */
    private const INDUSTRIES = [
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
    ];

    /**
     * Usage patterns grouped by zone (SPEC §4.2), verbatim.
     *
     * Note: the SPEC heading says "26" but the §4.2 table enumerates 32
     * patterns; the verbatim table is seeded here.
     *
     * @var array<string, list<string>>
     */
    private const USAGE_PATTERNS = [
        'Navigation' => ['Navbar', 'Footer', 'Sidebar', 'Mega Menu', 'Breadcrumb'],
        'Opening' => ['Hero', 'Logo Cloud', 'Announcement Banner'],
        'Content' => ['Feature Grid', 'About Section', 'Gallery', 'Team', 'Blog List', 'Blog Article', 'Stats'],
        'Social proof' => ['Testimonial', 'Reviews & Ratings', 'Case Study'],
        'Conversion' => ['Pricing', 'CTA', 'FAQ', 'Contact Form', 'Newsletter', 'Auth Forms'],
        'Commerce' => ['Product Card', 'Product Detail', 'Cart & Checkout'],
        'App UI' => ['Dashboard Card', 'Data Table', 'Modal & Dialog', 'Alerts & Toast', 'Empty State & 404'],
    ];

    public function run(): void
    {
        $sortOrder = 0;

        foreach (self::INDUSTRIES as $name) {
            Category::query()->firstOrCreate(
                ['type' => CategoryType::Industry->value, 'slug' => Str::slug($name)],
                ['zone' => null, 'name' => $name, 'sort_order' => $sortOrder++],
            );
        }

        $sortOrder = 0;

        foreach (self::USAGE_PATTERNS as $zone => $patterns) {
            foreach ($patterns as $name) {
                Category::query()->firstOrCreate(
                    ['type' => CategoryType::Usage->value, 'slug' => Str::slug($name)],
                    ['zone' => $zone, 'name' => $name, 'sort_order' => $sortOrder++],
                );
            }
        }
    }
}
