<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Home page: minimal marketing landing — hero, feature cards and CTAs to
 * pricing and registration, rendered from resources/js/pages/home.tsx.
 * Static enough for the public cache headers.
 */
class HomeController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $appName = config('app.name');

        return $this->cachedResponse(Inertia::render('home', [
            'meta' => [
                'title' => "{$appName} — Start your next product here",
                'description' => "{$appName} is a subscription SaaS with plans for individuals and teams — sign up and get started in minutes.",
                'canonical' => URL::to('/'),
                'og_image' => URL::to('/brand/logo.png'),
            ],
        ]), $request);
    }
}
