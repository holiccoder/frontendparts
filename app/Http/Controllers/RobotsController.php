<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

/**
 * `/robots.txt` (SPEC §10.2, §15.6): allow the public zone, disallow the
 * private CSR zones, reference the sitemap.
 */
class RobotsController extends Controller
{
    public function __invoke(): Response
    {
        $lines = [
            'User-agent: *',
            'Allow: /',
            'Disallow: /dashboard',
            'Disallow: /checkout',
            'Disallow: /settings',
            'Disallow: /admin',
            '',
            'Sitemap: '.route('sitemap'),
            '',
        ];

        return response(implode("\n", $lines), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
