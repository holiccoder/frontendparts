<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rendering zones (SPEC §10.1): Inertia SSR stays enabled globally, but
 * dashboard and checkout route groups flip the SSR gateway off per-request
 * (runtime config flip, no separate app). The X-SSR-Skipped header is sent
 * outside production so tests and local debugging can observe the zone.
 */
class SkipInertiaSsr
{
    public function handle(Request $request, Closure $next): Response
    {
        config(['inertia.ssr.enabled' => false]);

        $response = $next($request);

        if (! app()->isProduction()) {
            $response->headers->set('X-SSR-Skipped', '1');
        }

        return $response;
    }
}
