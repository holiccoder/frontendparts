<?php

namespace Tests\Feature\Ops;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class MailConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_mailer_is_not_log(): void
    {
        Config::set('app.env', 'production');

        $this->assertNotSame(
            'log',
            config('mail.default'),
            'Production mailer must be configured to a real provider (resend/postmark/smtp), not log.'
        );
    }
}
