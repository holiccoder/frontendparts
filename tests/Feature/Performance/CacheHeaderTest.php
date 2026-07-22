<?php

namespace Tests\Feature\Performance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CacheHeaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_sends_sensible_cache_headers(): void
    {
        $response = $this->get('/');

        $response->assertOk();

        $cacheControl = (string) $response->headers->get('Cache-Control');

        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=3600', $cacheControl);
        $this->assertStringContainsString('s-maxage=3600', $cacheControl);
    }

    public function test_legal_pages_send_sensible_cache_headers(): void
    {
        $response = $this->get('/terms');

        $response->assertOk();

        $cacheControl = (string) $response->headers->get('Cache-Control');

        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=3600', $cacheControl);
        $this->assertStringContainsString('s-maxage=3600', $cacheControl);
    }
}
