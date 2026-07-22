<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

abstract class Controller
{
    /**
     * Return an Inertia response with public CDN/browser cache headers.
     *
     * @param  int  $maxAge  Cache lifetime in seconds.
     */
    protected function cachedResponse(InertiaResponse $response, Request $request, int $maxAge = 3600): Response
    {
        return $response->toResponse($request)->withHeaders([
            'Cache-Control' => "public, max-age={$maxAge}, s-maxage={$maxAge}",
        ]);
    }
}
