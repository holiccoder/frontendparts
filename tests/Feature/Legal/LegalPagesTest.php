<?php

namespace Tests\Feature\Legal;

use App\Facades\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Legal pages (SPEC §15.7, §15.1): seven SSR, SEO-indexed pages rendered
 * from markdown in resources/legal/ — terms, privacy (GDPR + CCPA/CPRA +
 * PIPL), license (§7.4), refund policy (settings-driven window),
 * cookie policy, copyright & takedown (deep-links the takedown ticket
 * category) and legal notice. The footer links all seven from every
 * public page.
 */
class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<string, string>
     */
    private const PAGES = [
        '/terms' => 'Terms of Service',
        '/privacy' => 'Privacy Policy',
        '/license' => 'Component License',
        '/refund-policy' => 'Refund Policy',
        '/cookie-policy' => 'Cookie Policy',
        '/copyright' => 'Copyright & Takedown Policy',
        '/legal-notice' => 'Legal Notice',
    ];

    public function test_all_seven_pages_ssr_200()
    {
        foreach (self::PAGES as $path => $title) {
            $this->get($path)
                ->assertOk()
                ->assertHeaderMissing('X-SSR-Skipped')
                ->assertInertia(fn (Assert $page) => $page
                    ->component('legal/show')
                    ->where('page.title', $title)
                    ->has('page.html')
                    ->has('page.toc')
                    ->where('meta.canonical', url($path))
                );
        }
    }

    public function test_pages_are_indexed_no_noindex()
    {
        foreach (self::PAGES as $path => $title) {
            $response = $this->get($path)->assertOk();

            // No noindex header (the noindex middleware stays off this zone)…
            $response->assertHeaderMissing('X-Robots-Tag');

            // …and no noindex robots meta in the page's SEO props.
            $meta = $response->viewData('page')['props']['meta'];

            $this->assertArrayNotHasKey('robots', $meta, "{$path} must not carry a robots meta");
        }
    }

    public function test_footer_contains_all_links()
    {
        // The footer renders the shared legalNav prop on every public page —
        // assert it via the home page response.
        $props = $this->get('/')->assertOk()->viewData('page')['props'];

        $links = collect($props['legalNav'])->keyBy('url');

        foreach (self::PAGES as $path => $title) {
            $link = $links->get(url($path));

            $this->assertNotNull($link, "Footer is missing the {$title} link");
            $this->assertSame($title, $link['title']);
        }
    }

    public function test_copyright_page_links_takedown_ticket_category()
    {
        $html = $this->get('/copyright')
            ->assertOk()
            ->viewData('page')['props']['page']['html'];

        // The takedown procedure deep-links the ticket form with the
        // takedown category preselected (SPEC §9, §13.3).
        $this->assertStringContainsString('/dashboard/tickets/new?category=takedown', $html);
    }

    public function test_refund_window_comes_from_settings()
    {
        $html = $this->get('/refund-policy')
            ->assertOk()
            ->viewData('page')['props']['page']['html'];

        // Default window (SPEC §8.7 knob billing.refund_window_days = 14).
        $this->assertStringContainsString('14-day refund window', $html);

        Settings::set('billing.refund_window_days', 30);

        $html = $this->get('/refund-policy')
            ->assertOk()
            ->viewData('page')['props']['page']['html'];

        $this->assertStringContainsString('30-day refund window', $html);
    }

    public function test_legal_pages_in_sitemap()
    {
        $content = $this->get('/sitemap.xml')->assertOk()->getContent();

        foreach (self::PAGES as $path => $title) {
            $this->assertStringContainsString('<loc>'.url($path).'</loc>', $content);
        }
    }

    public function test_unknown_legal_page_404s()
    {
        $this->get('/legal-noticee')->assertNotFound();
    }
}
