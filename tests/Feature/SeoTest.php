<?php

namespace Tests\Feature;

use App\Models\Blog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_sitemap_contains_chassis_urls()
    {
        $post = Blog::factory()->published()->create();

        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $this->assertStringContainsString('application/xml', (string) $response->headers->get('Content-Type'));

        $content = $response->getContent();

        // Static + content-driven chassis URLs.
        $this->assertStringContainsString('<loc>'.url('/').'</loc>', $content);
        $this->assertStringContainsString('<loc>'.route('pricing').'</loc>', $content);
        $this->assertStringContainsString('<loc>'.route('legal.terms').'</loc>', $content);
        $this->assertStringContainsString('<loc>'.route('legal.affiliate-terms').'</loc>', $content);
        $this->assertStringContainsString('<loc>'.route('docs.show', ['section' => 'getting-started', 'page' => 'index']).'</loc>', $content);
        $this->assertStringContainsString('<loc>'.route('blog.index').'</loc>', $content);
        $this->assertStringContainsString('<loc>'.$post->publicUrl().'</loc>', $content);

        // No catalog-product URLs remain.
        $this->assertStringNotContainsString('/components', $content);
        $this->assertStringNotContainsString('/industries', $content);
        $this->assertStringNotContainsString('/collections', $content);
    }

    public function test_robots_disallows_private_zones()
    {
        $response = $this->get('/robots.txt');

        $response->assertOk();
        $this->assertStringContainsString('text/plain', (string) $response->headers->get('Content-Type'));

        $content = $response->getContent();

        foreach (['/dashboard', '/checkout', '/settings', '/admin'] as $zone) {
            $this->assertStringContainsString("Disallow: {$zone}", $content);
        }

        $this->assertStringContainsString('Sitemap: '.route('sitemap'), $content);
    }
}
