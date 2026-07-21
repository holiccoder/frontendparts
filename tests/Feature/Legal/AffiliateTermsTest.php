<?php

namespace Tests\Feature\Legal;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Affiliate Program Terms (SPEC §17.7): the 8th legal page — SSR and
 * SEO-indexed like the rest of the legal zone, linked from the footer and
 * the sitemap through the LegalPages registry, with the program knobs
 * (commission rate, threshold…) interpolated from platform settings. The
 * join flow records terms acceptance (§17.1 step 1).
 */
class AffiliateTermsTest extends TestCase
{
    use RefreshDatabase;

    public function test_terms_page_200_indexed()
    {
        $response = $this->get('/affiliate-terms')
            ->assertOk()
            // SSR like every legal page (no skip header, no noindex).
            ->assertHeaderMissing('X-SSR-Skipped')
            ->assertHeaderMissing('X-Robots-Tag')
            ->assertInertia(fn (Assert $page) => $page
                ->component('legal/show')
                ->where('page.title', 'Affiliate Program Terms')
                ->where('meta.canonical', url('/affiliate-terms'))
                ->has('page.html')
                ->has('page.toc')
            );

        $meta = $response->viewData('page')['props']['meta'];
        $this->assertArrayNotHasKey('robots', $meta);

        $html = $response->viewData('page')['props']['page']['html'];

        // §17.7 content: FTC disclosure, no brand-bidding, clawbacks.
        $this->assertStringContainsString('FTC', $html);
        $this->assertStringContainsString('Brand-bidding', $html);
        $this->assertStringContainsStringIgnoringCase('clawback', $html);

        // The program knobs are interpolated from settings, never hardcoded.
        $this->assertStringContainsString('30% of the net amount', $html);
        $this->assertStringContainsString('$50', $html);
        $this->assertStringContainsString('30-day holding period', $html);
        $this->assertStringNotContainsString('{{', $html);

        // Footer link flows from the shared legalNav prop.
        $props = $this->get('/')->assertOk()->viewData('page')['props'];
        $link = collect($props['legalNav'])->keyBy('url')->get(url('/affiliate-terms'));

        $this->assertNotNull($link, 'Footer is missing the Affiliate Program Terms link');
        $this->assertSame('Affiliate Program Terms', $link['title']);

        // Sitemap entry flows from the same registry.
        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee('<loc>'.url('/affiliate-terms').'</loc>', false);
    }

    public function test_join_requires_terms_acceptance()
    {
        $user = User::factory()->create();

        // No terms checkbox → rejected, no affiliate row.
        $this->actingAs($user)
            ->post(route('dashboard.affiliate.join'), [])
            ->assertSessionHasErrors('terms');

        $this->assertNull($user->refresh()->affiliate);

        // Explicitly declining is rejected too.
        $this->actingAs($user)
            ->post(route('dashboard.affiliate.join'), ['terms' => 'no'])
            ->assertSessionHasErrors('terms');

        $this->assertNull($user->refresh()->affiliate);
    }
}
