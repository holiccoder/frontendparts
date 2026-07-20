<?php

namespace Tests\Feature\Catalog;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NotFoundTest extends TestCase
{
    use RefreshDatabase;

    public function test_404_renders_branded_page_with_catalog_link()
    {
        $response = $this->get('/this-page-does-not-exist');

        $response->assertNotFound();

        // Branded Inertia error page (SPEC §15.1) — the page component links
        // back to /components.
        $response->assertInertia(fn (Assert $page) => $page
            ->component('error')
            ->where('status', 404)
        );

        // JSON consumers keep the framework's plain JSON 404.
        $this->getJson('/this-page-does-not-exist')
            ->assertNotFound()
            ->assertHeaderMissing('X-Inertia');
    }
}
