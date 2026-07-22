<?php

namespace Tests\Feature\Ops;

use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_health_endpoint_200(): void
    {
        $response = $this->get('/up');

        $response->assertOk();
        $response->assertSee('Application up');
    }
}
